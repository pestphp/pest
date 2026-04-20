<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Plugins\Tia;
use Pest\Plugins\Tia\Contracts\State;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Pulls a team-shared TIA baseline on the first `--tia` run so new
 * contributors and fresh CI workspaces start in replay mode instead of
 * paying the ~30s record cost.
 *
 * The baseline lives as a GitHub Release with a fixed tag containing two
 * assets — the graph JSON and the coverage cache. The repo is inferred
 * from `.git/config`'s `origin` remote, so no per-project configuration
 * is required. Non-GitHub remotes silently opt out.
 *
 * Fetching is attempted in order:
 *   1. `gh release download` — uses the user's existing GitHub auth,
 *      works for private repos.
 *   2. Plain HTTPS — public-repo fallback when `gh` isn't installed.
 *
 * Fingerprint validation happens back in `Tia::handleParent` after the
 * blobs are written: a mismatched environment (different PHP version,
 * composer.lock, etc.) discards the pulled baseline and falls through to
 * the regular record path.
 *
 * @internal
 */
final class BaselineSync
{
    /**
     * Conventional tag the CI recipe publishes under. Not configurable for
     * MVP — if teams outgrow the convention, a `PEST_TIA_BASELINE_TAG` env
     * var is the likely escape hatch.
     */
    private const string RELEASE_TAG = 'pest-tia-baseline';

    /**
     * Asset filenames within the release — mirror the state keys so the
     * CI publisher and the sync consumer stay in lock-step.
     */
    private const string GRAPH_ASSET = Tia::KEY_GRAPH;

    private const string COVERAGE_ASSET = Tia::KEY_COVERAGE_CACHE;

    public function __construct(
        private readonly State $state,
        private readonly OutputInterface $output,
        private readonly InputInterface $input,
    ) {}

    /**
     * Attempts the full detect → prompt → download flow. Returns true when
     * the graph blob was pulled and written to state. Coverage is best-
     * effort: its absence doesn't fail the sync, since plain `--tia` (no
     * `--coverage`) works fine without it.
     */
    public function fetchIfAvailable(string $projectRoot): bool
    {
        $repo = $this->detectGitHubRepo($projectRoot);

        if ($repo === null) {
            return false;
        }

        if (! $this->confirm($repo)) {
            return false;
        }

        $this->output->writeln(sprintf(
            '  <fg=cyan>TIA</> fetching baseline from <fg=white>%s</>…',
            $repo,
        ));

        $graphJson = $this->download($repo, self::GRAPH_ASSET);

        if ($graphJson === null) {
            $this->output->writeln(
                '  <fg=yellow>TIA</> no baseline published yet — recording locally.',
            );

            return false;
        }

        if (! $this->state->write(Tia::KEY_GRAPH, $graphJson)) {
            return false;
        }

        // Coverage cache is optional. The baseline is useful even without
        // it (plain `--tia` never needs it) so don't fail the whole sync
        // just because this asset is missing or slow.
        $coverageBin = $this->download($repo, self::COVERAGE_ASSET);

        if ($coverageBin !== null) {
            $this->state->write(Tia::KEY_COVERAGE_CACHE, $coverageBin);
        }

        $this->output->writeln(sprintf(
            '  <fg=green>TIA</> baseline ready (%s).',
            $this->formatSize(strlen($graphJson) + strlen($coverageBin ?? '')),
        ));

        return true;
    }

    /**
     * Publishes the *local* baseline to GitHub Releases under the
     * conventional tag, creating the release on first run or uploading
     * into the existing one otherwise.
     *
     * Uploading from a developer workstation is intentionally discouraged
     * — CI is the authoritative publisher because its environment is
     * reproducible, its working tree is clean, and its result cache
     * isn't contaminated by local flakiness. The prompt here defaults to
     * *No* to keep this an explicit, opt-in action.
     *
     * Returns a CLI-style exit code so the caller can `exit()` on it.
     */
    public function publish(string $projectRoot): int
    {
        $graphBytes = $this->state->read(Tia::KEY_GRAPH);

        if ($graphBytes === null) {
            $this->output->writeln([
                '',
                '  <fg=red>TIA</> no local baseline to publish.',
                '  Run <fg=cyan>./vendor/bin/pest --tia</> first to record one, then retry.',
                '',
            ]);

            return 1;
        }

        $repo = $this->detectGitHubRepo($projectRoot);

        if ($repo === null) {
            $this->output->writeln([
                '',
                '  <fg=red>TIA</> cannot infer a GitHub repo from <fg=gray>.git/config</>.',
                '  Publishing is supported only for GitHub-hosted projects.',
                '',
            ]);

            return 1;
        }

        if (! $this->commandExists('gh')) {
            $this->output->writeln([
                '',
                '  <fg=red>TIA</> publishing requires the <fg=cyan>gh</> CLI.',
                '  Install: <fg=gray>https://cli.github.com</>',
                '',
            ]);

            return 1;
        }

        $this->output->writeln([
            '',
            '  <fg=black;bg=yellow> WARNING </> Publishing local baselines is discouraged.',
            '',
            '  Local runs can bake flaky results or dirty working-tree state into the',
            '  baseline, which your team then replays. CI-published baselines are safer.',
            '  See <fg=gray>https://pestphp.com/docs/tia/ci</> for the recommended workflow.',
            '',
        ]);

        if (! $this->confirmPublish($repo)) {
            $this->output->writeln('  <fg=yellow>TIA</> publish cancelled.');

            return 0;
        }

        $tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-tia-publish-'.bin2hex(random_bytes(4));

        if (! @mkdir($tmpDir, 0755, true) && ! is_dir($tmpDir)) {
            $this->output->writeln('  <fg=red>TIA</> failed to create temp dir for upload.');

            return 1;
        }

        $graphPath = $tmpDir.DIRECTORY_SEPARATOR.self::GRAPH_ASSET;

        if (@file_put_contents($graphPath, $graphBytes) === false) {
            $this->cleanup($tmpDir);

            return 1;
        }

        $filesToUpload = [$graphPath];

        $coverageBytes = $this->state->read(Tia::KEY_COVERAGE_CACHE);

        if ($coverageBytes !== null) {
            $coveragePath = $tmpDir.DIRECTORY_SEPARATOR.self::COVERAGE_ASSET;

            if (@file_put_contents($coveragePath, $coverageBytes) !== false) {
                $filesToUpload[] = $coveragePath;
            }
        }

        $this->output->writeln(sprintf(
            '  <fg=cyan>TIA</> publishing to <fg=white>%s</> (tag <fg=white>%s</>)…',
            $repo,
            self::RELEASE_TAG,
        ));

        $exitCode = $this->ghReleaseUploadOrCreate($repo, $filesToUpload);
        $this->cleanup($tmpDir);

        if ($exitCode !== 0) {
            $this->output->writeln('  <fg=red>TIA</> <fg=cyan>gh release</> failed.');

            return $exitCode;
        }

        $this->output->writeln(sprintf(
            '  <fg=green>TIA</> baseline published (%s).',
            $this->formatSize(strlen($graphBytes) + ($coverageBytes === null ? 0 : strlen($coverageBytes))),
        ));

        return 0;
    }

    /**
     * Uploads into the existing release if present, falls back to
     * creating the release with the assets attached on first run.
     *
     * @param  array<int, string>  $files
     */
    private function ghReleaseUploadOrCreate(string $repo, array $files): int
    {
        $uploadArgs = ['gh', 'release', 'upload', self::RELEASE_TAG, ...$files, '-R', $repo, '--clobber'];
        $upload = new Process($uploadArgs);
        $upload->setTimeout(300.0);
        $upload->run(function (string $_, string $buffer): void {
            $this->output->write($buffer);
        });

        if ($upload->isSuccessful()) {
            return 0;
        }

        // Release likely doesn't exist yet — create it, attaching the files.
        $createArgs = [
            'gh', 'release', 'create', self::RELEASE_TAG,
            ...$files,
            '-R', $repo,
            '--title', 'Pest TIA baseline',
            '--notes', 'Machine-generated baseline for Pest TIA. Do not edit manually.',
        ];
        $create = new Process($createArgs);
        $create->setTimeout(300.0);
        $create->run(function (string $_, string $buffer): void {
            $this->output->write($buffer);
        });

        return $create->isSuccessful() ? 0 : 1;
    }

    private function confirmPublish(string $repo): bool
    {
        if (! $this->isTerminal()) {
            return false;
        }

        $this->output->writeln(sprintf(
            '  Publish to <fg=white>%s</> (tag <fg=white>%s</>)? <fg=gray>[y/N]</>',
            $repo,
            self::RELEASE_TAG,
        ));

        $handle = @fopen('php://stdin', 'r');

        if ($handle === false) {
            return false;
        }

        $line = fgets($handle);
        fclose($handle);

        if ($line === false) {
            return false;
        }

        // Unlike the fetch prompt, this one defaults to *No*. Empty input
        // or anything other than an explicit "y"/"yes" cancels.
        $line = strtolower(trim($line));

        return $line === 'y' || $line === 'yes';
    }

    /**
     * Parses `.git/config` for the `origin` remote and extracts
     * `org/repo`. Supports the two URL flavours git emits out of the box.
     * Non-GitHub remotes (GitLab, Bitbucket, self-hosted) → null, which
     * silently opts the repo out of auto-sync.
     */
    private function detectGitHubRepo(string $projectRoot): ?string
    {
        $gitConfig = $projectRoot.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'config';

        if (! is_file($gitConfig)) {
            return null;
        }

        $content = @file_get_contents($gitConfig);

        if ($content === false) {
            return null;
        }

        // Find the `[remote "origin"]` section and the first `url` line
        // inside it. Tolerates INI whitespace quirks (tabs, CRLF).
        if (preg_match('/\[remote "origin"\][^\[]*?url\s*=\s*(\S+)/s', $content, $match) !== 1) {
            return null;
        }

        $url = $match[1];

        // SSH: git@github.com:org/repo(.git)
        if (preg_match('#^git@github\.com:([\w.-]+/[\w.-]+?)(?:\.git)?$#', $url, $m) === 1) {
            return $m[1];
        }

        // HTTPS: https://github.com/org/repo(.git) (optional trailing slash)
        if (preg_match('#^https?://github\.com/([\w.-]+/[\w.-]+?)(?:\.git)?/?$#', $url, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * One-shot Y/n prompt. Defaults to Y. In non-interactive shells (CI,
     * piped input) returns false so scripted runs never hang waiting for
     * input.
     */
    private function confirm(string $repo): bool
    {
        if (! $this->isTerminal()) {
            return false;
        }

        $this->output->writeln('');
        $this->output->writeln(sprintf(
            '  <fg=cyan>TIA</> no local cache — fetch baseline from <fg=white>%s</>? <fg=gray>[Y/n]</>',
            $repo,
        ));

        $handle = @fopen('php://stdin', 'r');

        if ($handle === false) {
            return false;
        }

        $line = fgets($handle);
        fclose($handle);

        if ($line === false) {
            return false;
        }

        $line = strtolower(trim($line));

        return $line === '' || $line === 'y' || $line === 'yes';
    }

    /**
     * Real-TTY check for STDIN. Symfony's `isInteractive()` defaults to true
     * unless `--no-interaction` is explicitly passed, which would make
     * scripted invocations (CI, pipes, subshells) hang at a prompt nobody
     * sees. Combining both signals is the safe default.
     */
    private function isTerminal(): bool
    {
        if (! $this->input->isInteractive()) {
            return false;
        }

        if (! defined('STDIN')) {
            return false;
        }

        if (function_exists('posix_isatty')) {
            return @posix_isatty(STDIN) === true;
        }

        if (function_exists('stream_isatty')) {
            return @stream_isatty(STDIN) === true;
        }

        return false;
    }

    /**
     * Tries `gh` first (handles private repos + rate limiting via the
     * user's GitHub auth), falls through to public HTTPS. Returns the
     * raw asset bytes, or null on any failure.
     */
    private function download(string $repo, string $asset): ?string
    {
        $viaGh = $this->downloadViaGh($repo, $asset);

        if ($viaGh !== null) {
            return $viaGh;
        }

        return $this->downloadViaHttps($repo, $asset);
    }

    private function downloadViaGh(string $repo, string $asset): ?string
    {
        if (! $this->commandExists('gh')) {
            return null;
        }

        $tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-tia-'.bin2hex(random_bytes(4));

        if (! @mkdir($tmpDir, 0755, true) && ! is_dir($tmpDir)) {
            return null;
        }

        $process = new Process([
            'gh', 'release', 'download', self::RELEASE_TAG,
            '-R', $repo,
            '-p', $asset,
            '-D', $tmpDir,
            '--clobber',
        ]);
        $process->setTimeout(120.0);
        $process->run();

        $payload = null;

        if ($process->isSuccessful()) {
            $path = $tmpDir.DIRECTORY_SEPARATOR.$asset;

            if (is_file($path)) {
                $content = @file_get_contents($path);
                $payload = $content === false ? null : $content;
            }
        }

        $this->cleanup($tmpDir);

        return $payload;
    }

    private function downloadViaHttps(string $repo, string $asset): ?string
    {
        $url = sprintf(
            'https://github.com/%s/releases/download/%s/%s',
            $repo,
            self::RELEASE_TAG,
            $asset,
        );

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 120,
                'follow_location' => 1,
                'ignore_errors' => false,
            ],
        ]);

        $content = @file_get_contents($url, false, $ctx);

        return $content === false ? null : $content;
    }

    private function commandExists(string $cmd): bool
    {
        $probe = new Process(['command', '-v', $cmd]);
        $probe->run();

        if ($probe->isSuccessful()) {
            return true;
        }

        $which = new Process(['which', $cmd]);
        $which->run();

        return $which->isSuccessful();
    }

    private function cleanup(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $entries = glob($dir.DIRECTORY_SEPARATOR.'*');

        if ($entries !== false) {
            foreach ($entries as $entry) {
                if (is_file($entry)) {
                    @unlink($entry);
                }
            }
        }

        @rmdir($dir);
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return sprintf('%.1f MB', $bytes / 1024 / 1024);
        }

        if ($bytes >= 1024) {
            return sprintf('%.1f KB', $bytes / 1024);
        }

        return $bytes.' B';
    }
}
