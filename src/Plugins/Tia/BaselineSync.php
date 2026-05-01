<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Composer\InstalledVersions;
use Pest\Exceptions\BaselineFetchFailed;
use Pest\Panic;
use Pest\Plugins\Tia;
use Pest\Plugins\Tia\Contracts\State;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Downloads a team-shared TIA baseline from GitHub workflow artifacts so new contributors and
 * fresh CI workspaces start in replay mode. Artifacts are used instead of releases because they
 * produce no tag (no push cascade), support tunable retention, and can only be published by CI.
 *
 * Fingerprint validation happens in `Tia::handleParent` after the blobs land; a mismatched
 * environment falls through to the normal record path.
 *
 * @internal
 */
final readonly class BaselineSync
{
    private const string WORKFLOW_FILE = 'tia-baseline.yml';

    private const string ARTIFACT_NAME = 'pest-tia-baseline';

    private const string GRAPH_ASSET = Tia::KEY_GRAPH;

    private const string COVERAGE_ASSET = Tia::KEY_COVERAGE_CACHE;

    // Subdirectory under the per-project state dir (`~/.pest/tia/<project>/`)
    // where artifacts from previous downloads are kept (one subfolder per
    // workflow run id). Hitting the same run id on a later fetch skips
    // the `gh run download` round trip entirely — artifacts are immutable
    // per run id, so the cached bytes are exactly what gh would re-download.
    private const string DOWNLOAD_CACHE_DIR = 'artifacts';

    // Most recently downloaded artifacts to retain on disk. Branch
    // switches and partial baseline rollouts hop across run ids — keeping
    // the last few avoids re-downloading when the user toggles between
    // them. Older entries get evicted on the next download.
    private const int DOWNLOAD_CACHE_MAX_ENTRIES = 5;

    // 24 h cooldown after a failed fetch so repeated `pest --tia` calls don't re-hit `gh run list`.
    private const int FETCH_COOLDOWN_SECONDS = 86400;

    public function __construct(
        private State $state,
        private OutputInterface $output,
    ) {}

    public function fetchIfAvailable(string $projectRoot, bool $force = false): bool
    {
        $repo = $this->detectGitHubRepo($projectRoot);

        if ($repo === null) {
            return false;
        }

        if (! $force && ($remaining = $this->cooldownRemaining()) !== null) {
            $this->output->writeln(sprintf(
                '  <fg=yellow>TIA</> last fetch found no baseline — next auto-retry in %s. '
                    .'Override with <fg=cyan>--refetch</>.',
                $this->formatDuration($remaining),
            ));

            return false;
        }

        $failureKind = null;
        $payload = $this->download($repo, $projectRoot, $failureKind);

        if ($payload === null) {
            // Genuine "no baseline published yet" → cool down and show
            // the publish-instructions YAML so the user can wire CI.
            // Anything else (missing gh, auth, network, mid-download
            // error) is transient and gets a one-line diagnostic
            // instead — no cooldown, no noisy YAML.
            if ($failureKind === 'no-runs' || $failureKind === null) {
                $this->startCooldown();
                $this->emitPublishInstructions($repo);
            }

            return false;
        }

        if (! $this->state->write(Tia::KEY_GRAPH, $payload['graph'])) {
            return false;
        }

        if ($payload['coverage'] !== null) {
            $this->state->write(Tia::KEY_COVERAGE_CACHE, $payload['coverage']);
        }

        $this->clearCooldown();

        $this->output->writeln(sprintf(
            '  <fg=green>TIA</> baseline ready (%s).',
            $this->formatSize(strlen($payload['graph']) + strlen($payload['coverage'] ?? '')),
        ));

        return true;
    }

    private function cooldownRemaining(): ?int
    {
        $raw = $this->state->read(Tia::KEY_FETCH_COOLDOWN);

        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || ! isset($decoded['until']) || ! is_int($decoded['until'])) {
            return null;
        }

        $remaining = $decoded['until'] - time();

        return $remaining > 0 ? $remaining : null;
    }

    private function startCooldown(): void
    {
        $this->state->write(Tia::KEY_FETCH_COOLDOWN, (string) json_encode([
            'until' => time() + self::FETCH_COOLDOWN_SECONDS,
        ]));
    }

    private function clearCooldown(): void
    {
        $this->state->delete(Tia::KEY_FETCH_COOLDOWN);
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds >= 3600) {
            return (int) round($seconds / 3600).'h';
        }

        if ($seconds >= 60) {
            return (int) round($seconds / 60).'m';
        }

        return $seconds.'s';
    }

    private function emitPublishInstructions(string $repo): void
    {
        if ($this->isCi()) {
            $this->output->writeln(
                '  <fg=yellow>TIA</> no baseline yet — this run will produce one.',
            );

            return;
        }

        $yaml = $this->isLaravel()
            ? $this->laravelWorkflowYaml()
            : $this->genericWorkflowYaml();

        $preamble = [
            '  <fg=yellow>TIA</> no baseline published yet — recording locally.',
            '',
            '  To share the baseline with your team, add this workflow to the repo:',
            '',
            '    <fg=cyan>.github/workflows/tia-baseline.yml</>',
            '',
        ];

        $indentedYaml = array_map(
            static fn (string $line): string => '      '.$line,
            explode("\n", $yaml),
        );

        $trailer = [
            '',
            sprintf('  Commit, push, then run once:  <fg=cyan>gh workflow run tia-baseline.yml -R %s</>', $repo),
            '  Details: <fg=gray>https://pestphp.com/docs/tia/ci</>',
            '',
        ];

        $this->output->writeln([...$preamble, ...$indentedYaml, ...$trailer]);
    }

    // `CI=true` alone is ambiguous (users set it locally) — require a provider-specific env var.
    private function isCi(): bool
    {
        return getenv('GITHUB_ACTIONS') === 'true'
            || getenv('GITLAB_CI') === 'true'
            || getenv('CIRCLECI') === 'true';
    }

    private function isLaravel(): bool
    {
        return class_exists(InstalledVersions::class)
            && InstalledVersions::isInstalled('laravel/framework');
    }

    private function laravelWorkflowYaml(): string
    {
        return <<<'YAML'
name: TIA Baseline
on:
  push: { branches: [main] }
  schedule: [{ cron: '0 3 * * *' }]
  workflow_dispatch:
jobs:
  baseline:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with: { fetch-depth: 0 }
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: xdebug
          extensions: json, dom, curl, libxml, mbstring, zip, pdo, pdo_sqlite, sqlite3, bcmath, intl
      - run: cp .env.example .env
      - run: composer install --no-interaction --prefer-dist
      - run: php artisan key:generate
      - run: ./vendor/bin/pest --parallel --tia --coverage
      - name: Stage baseline for upload
        shell: bash
        run: |
          mkdir -p .pest-tia-baseline
          cp -R "$HOME/.pest/tia"/*/. .pest-tia-baseline/
      - uses: actions/upload-artifact@v4
        with:
          name: pest-tia-baseline
          path: .pest-tia-baseline/
          retention-days: 30
YAML;
    }

    private function genericWorkflowYaml(): string
    {
        return <<<'YAML'
name: TIA Baseline
on:
  push: { branches: [main] }
  schedule: [{ cron: '0 3 * * *' }]
  workflow_dispatch:
jobs:
  baseline:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with: { fetch-depth: 0 }
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.4', coverage: xdebug }
      - run: composer install --no-interaction --prefer-dist
      - run: ./vendor/bin/pest --parallel --tia --coverage
      - name: Stage baseline for upload
        shell: bash
        run: |
          mkdir -p .pest-tia-baseline
          cp -R "$HOME/.pest/tia"/*/. .pest-tia-baseline/
      - uses: actions/upload-artifact@v4
        with:
          name: pest-tia-baseline
          path: .pest-tia-baseline/
          retention-days: 30
YAML;
    }

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

        if (preg_match('/\[remote "origin"\][^\[]*?url\s*=\s*(\S+)/s', $content, $match) !== 1) {
            return null;
        }

        $url = $match[1];

        if (preg_match('#^git@github\.com:([\w.-]+/[\w.-]+?)(?:\.git)?$#', $url, $m) === 1) {
            return $m[1];
        }

        if (preg_match('#^https?://github\.com/([\w.-]+/[\w.-]+?)(?:\.git)?/?$#', $url, $m) === 1) {
            return $m[1];
        }

        if (preg_match('#^ssh://(?:[^@/]+@)?github\.com(?::\d+)?/([\w.-]+/[\w.-]+?)(?:\.git)?/?$#i', $url, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * @param-out string|null $failureKind
     *
     * @return array{graph: string, coverage: ?string}|null
     */
    private function download(string $repo, string $projectRoot, ?string &$failureKind = null): ?array
    {
        $failureKind = null;

        if (! $this->commandExists('gh')) {
            Panic::with(new BaselineFetchFailed(
                'GitHub CLI (gh) not found — cannot fetch baseline.',
                'Install it from https://cli.github.com.',
            ));
        }

        if (! $this->ghAuthenticated()) {
            Panic::with(new BaselineFetchFailed(
                'GitHub CLI (gh) is not authenticated — cannot fetch baseline.',
                'Run `gh auth login` and retry.',
            ));
        }

        [$runId, $listError] = $this->latestSuccessfulRunIdWithError($repo);

        if ($listError !== null) {
            $failureKind = $listError['kind'];

            // Tier 1 — actionable misconfiguration. Stop the suite and
            // tell the user what to fix; a silent fall-through to a
            // full record would just paper over the bug.
            if (in_array($failureKind, ['forbidden', 'not-found'], true)) {
                Panic::with(new BaselineFetchFailed(
                    sprintf('Failed to query baseline runs — %s', $listError['message']),
                    'Check the workflow file name (tia-baseline.yml), artifact name (pest-tia-baseline), and your gh token scope.',
                ));
            }

            // Tier 2 — transient (network, rate-limit, unknown). Surface
            // the diagnostic but let the suite fall through to record mode.
            $this->output->writeln(sprintf(
                '  <fg=yellow>TIA</> failed to query baseline runs — %s',
                $listError['message'],
            ));

            return null;
        }

        if ($runId === null) {
            // Genuine missing baseline — caller emits publish instructions.
            $failureKind = 'no-runs';

            return null;
        }

        $runCacheDir = $this->downloadCacheDir($projectRoot).DIRECTORY_SEPARATOR.$this->safeRunId($runId);

        // Cache hit: a previous fetch already extracted this run id's
        // artifact into the run-specific dir. Read the assets straight
        // out of it and skip `gh run download` entirely.
        if (is_file($runCacheDir.DIRECTORY_SEPARATOR.self::GRAPH_ASSET)) {
            // Bump the dir mtime so trimDownloadCache() treats this run
            // id as recently used and doesn't evict it later.
            @touch($runCacheDir);

            $this->output->writeln(sprintf(
                '  <fg=cyan>TIA</> using cached baseline from <fg=white>%s</> (run %s).',
                $repo,
                $runId,
            ));

            return $this->readArtifact($runCacheDir);
        }

        if (! @mkdir($runCacheDir, 0755, true) && ! is_dir($runCacheDir)) {
            return null;
        }

        $artifactSize = $this->artifactSize($repo, $runId);

        $this->output->writeln($artifactSize !== null
            ? sprintf(
                '  <fg=cyan>TIA</> fetching baseline (%s) from <fg=white>%s</>…',
                $this->formatSize($artifactSize),
                $repo,
            )
            : sprintf(
                '  <fg=cyan>TIA</> fetching baseline from <fg=white>%s</>…',
                $repo,
            ));

        $process = new Process([
            'gh', 'run', 'download', $runId,
            '-R', $repo,
            '-n', self::ARTIFACT_NAME,
            '-D', $runCacheDir,
        ]);
        $process->setTimeout(900.0);
        $process->start();

        $startedAt = microtime(true);

        while ($process->isRunning()) {
            $this->renderDownloadProgress($runCacheDir, $artifactSize, $startedAt);
            usleep(250_000);
        }

        $process->wait();
        $this->clearProgressLine();

        if (! $process->isSuccessful()) {
            $this->cleanup($runCacheDir);

            $diagnosis = $this->classifyGhError($process->getErrorOutput().$process->getOutput());
            $failureKind = $diagnosis['kind'];

            // Tier 1 — actionable. Stop hard with a clear diagnostic.
            if (in_array($failureKind, ['forbidden', 'not-found'], true)) {
                Panic::with(new BaselineFetchFailed(
                    sprintf('Baseline download failed — %s', $diagnosis['message']),
                    'Check the workflow file name (tia-baseline.yml), artifact name (pest-tia-baseline), and your gh token scope.',
                ));
            }

            // Tier 2 — transient. Diagnostic + fall through to record mode.
            $this->output->writeln(sprintf(
                '  <fg=yellow>TIA</> baseline download failed — %s',
                $diagnosis['message'],
            ));

            return null;
        }

        $payload = $this->readArtifact($runCacheDir);

        if ($payload === null) {
            $this->cleanup($runCacheDir);

            // Artifact present but malformed — CI's publish step is
            // broken. Falling through would silently waste the next
            // run; surface the bug instead.
            Panic::with(new BaselineFetchFailed(
                'Baseline downloaded but the artifact is missing expected files (graph.json).',
                'Your CI publish step is broken — check the workflow that uploads pest-tia-baseline.',
            ));
        }

        $this->trimDownloadCache($projectRoot);

        return $payload;
    }

    /**
     * Looks up the artifact's compressed size so the progress bar has a
     * denominator. Returns null on any failure — callers fall back to a
     * size-less spinner.
     */
    private function artifactSize(string $repo, string $runId): ?int
    {
        $process = new Process([
            'gh', 'api',
            sprintf('repos/%s/actions/runs/%s/artifacts', $repo, $runId),
            '--jq', sprintf(
                '.artifacts[] | select(.name == "%s") | .size_in_bytes',
                self::ARTIFACT_NAME,
            ),
        ]);
        $process->setTimeout(30.0);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $size = trim($process->getOutput());

        return is_numeric($size) ? (int) $size : null;
    }

    private function renderDownloadProgress(string $dir, ?int $totalBytes, float $startedAt): void
    {
        $current = $this->dirSize($dir);
        $elapsed = max(0.001, microtime(true) - $startedAt);
        $speed = (int) ($current / $elapsed);

        if ($totalBytes !== null && $totalBytes > 0) {
            // gh extracts as it downloads, so disk size can briefly exceed
            // the compressed `size_in_bytes` for multi-file artifacts. Cap
            // the percentage at 99% until the process actually exits — the
            // cleared line + completion message take care of the final
            // "100%" message naturally.
            $percent = min(99, (int) floor(($current / $totalBytes) * 100));
            $message = sprintf(
                '  <fg=cyan>TIA</> downloading %s / %s (%d%%, %s/s)',
                $this->formatSize($current),
                $this->formatSize($totalBytes),
                $percent,
                $this->formatSize($speed),
            );
        } else {
            $message = sprintf(
                '  <fg=cyan>TIA</> downloading %s (%s/s)',
                $this->formatSize($current),
                $this->formatSize($speed),
            );
        }

        // \r returns to start of line, \033[K erases from cursor to end —
        // safe regardless of message length, no ANSI-aware padding needed.
        $this->output->write("\r\033[K".$message);
    }

    private function clearProgressLine(): void
    {
        $this->output->write("\r\033[K");
    }

    private function dirSize(string $dir): int
    {
        if (! is_dir($dir)) {
            return 0;
        }

        $total = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if ($entry->isFile()) {
                $total += $entry->getSize();
            }
        }

        return $total;
    }

    /**
     * @return array{graph: string, coverage: ?string}|null
     */
    private function readArtifact(string $dir): ?array
    {
        $graphPath = $dir.DIRECTORY_SEPARATOR.self::GRAPH_ASSET;
        $coveragePath = $dir.DIRECTORY_SEPARATOR.self::COVERAGE_ASSET;

        $graph = is_file($graphPath) ? @file_get_contents($graphPath) : false;

        if ($graph === false) {
            return null;
        }

        $coverage = is_file($coveragePath) ? @file_get_contents($coveragePath) : false;

        return [
            'graph' => $graph,
            'coverage' => $coverage === false ? null : $coverage,
        ];
    }

    private function downloadCacheDir(string $projectRoot): string
    {
        return Storage::tempDir($projectRoot).DIRECTORY_SEPARATOR.self::DOWNLOAD_CACHE_DIR;
    }

    /**
     * Run ids returned by `gh` are numeric strings, but defend against a
     * surprising response by stripping anything non-alphanumeric — the
     * value is used as a directory name.
     */
    private function safeRunId(string $runId): string
    {
        $sanitised = preg_replace('/[^A-Za-z0-9_-]/', '', $runId) ?? '';

        return $sanitised === '' ? 'unknown' : $sanitised;
    }

    /**
     * Keep the N most recently used cached artifacts and evict the rest.
     * Recency is taken from the directory mtime — `mkdir`/`gh run download`
     * stamps it on a fresh entry, and a cache hit `touch`es it back to
     * the front of the line, so a frequently-reused run id won't be
     * evicted just because newer ids have been seen between uses.
     */
    private function trimDownloadCache(string $projectRoot): void
    {
        $root = $this->downloadCacheDir($projectRoot);

        if (! is_dir($root)) {
            return;
        }

        $entries = @scandir($root);

        if ($entries === false) {
            return;
        }

        $candidates = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $root.DIRECTORY_SEPARATOR.$entry;

            if (! is_dir($path)) {
                continue;
            }

            $mtime = @filemtime($path);
            $candidates[] = ['path' => $path, 'mtime' => $mtime === false ? 0 : $mtime];
        }

        if (count($candidates) <= self::DOWNLOAD_CACHE_MAX_ENTRIES) {
            return;
        }

        usort(
            $candidates,
            static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime'],
        );

        foreach (array_slice($candidates, self::DOWNLOAD_CACHE_MAX_ENTRIES) as $stale) {
            $this->cleanup($stale['path']);
        }
    }

    /**
     * Returns `[runId|null, errorOrNull]`. Distinguishes "no runs yet"
     * (runId null, error null) from "couldn't ask GitHub" (error
     * populated with kind + message). Lets the caller pick between
     * showing publish instructions and emitting a transient-failure
     * diagnostic.
     *
     * @return array{0: ?string, 1: ?array{kind: string, message: string}}
     */
    private function latestSuccessfulRunIdWithError(string $repo): array
    {
        $process = new Process([
            'gh', 'run', 'list',
            '-R', $repo,
            '--workflow', self::WORKFLOW_FILE,
            '--status', 'success',
            '--limit', '1',
            '--json', 'databaseId',
            '--jq', '.[0].databaseId // empty',
        ]);
        $process->setTimeout(30.0);
        $process->run();

        if (! $process->isSuccessful()) {
            return [null, $this->classifyGhError($process->getErrorOutput().$process->getOutput())];
        }

        $runId = trim($process->getOutput());

        return [$runId === '' ? null : $runId, null];
    }

    private function ghAuthenticated(): bool
    {
        $process = new Process(['gh', 'auth', 'status']);
        $process->setTimeout(10.0);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Maps a chunk of `gh` stderr/stdout to a coarse kind + a short,
     * actionable message. Falls back to the first non-empty line of
     * the output so even unrecognised errors aren't reduced to "unknown".
     *
     * @return array{kind: string, message: string}
     */
    private function classifyGhError(string $output): array
    {
        $output = trim($output);

        if ($output === '') {
            return ['kind' => 'unknown', 'message' => 'unknown error'];
        }

        if (preg_match('/(could not resolve host|connection refused|connection reset|temporary failure in name resolution|network is unreachable|no route to host|i\/o timeout|tls handshake|getaddrinfo)/i', $output) === 1) {
            return [
                'kind' => 'network',
                'message' => 'network error (offline or DNS unreachable). Try again when connected.',
            ];
        }

        if (preg_match('/(authentication failed|not logged in|requires authentication|bad credentials|401)/i', $output) === 1) {
            return [
                'kind' => 'gh-auth',
                'message' => 'authentication failed — run `gh auth login` and retry.',
            ];
        }

        if (preg_match('/(rate limit|too many requests|secondary rate limit)/i', $output) === 1) {
            return [
                'kind' => 'rate-limit',
                'message' => 'GitHub API rate limit hit — try again later.',
            ];
        }

        if (preg_match('/(404|not found|repository not found)/i', $output) === 1) {
            return [
                'kind' => 'not-found',
                'message' => 'workflow or artifact not found in repo.',
            ];
        }

        if (preg_match('/(403|forbidden|access denied)/i', $output) === 1) {
            return [
                'kind' => 'forbidden',
                'message' => 'access denied — check that your `gh` token has repo + actions read scope.',
            ];
        }

        // Unknown — surface the first informative line so the user has
        // *something* to act on.
        $first = strtok($output, "\n");
        $message = is_string($first) ? trim($first) : 'unknown error';

        return ['kind' => 'unknown', 'message' => $message];
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
