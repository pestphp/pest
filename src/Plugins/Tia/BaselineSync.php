<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Composer\InstalledVersions;
use Pest\Plugins\Tia;
use Pest\Plugins\Tia\Contracts\State;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Pulls a team-shared TIA baseline on the first `--tia` run so new
 * contributors and fresh CI workspaces start in replay mode instead of
 * paying the ~30s record cost.
 *
 * Storage: **workflow artifacts**, not releases. A dedicated CI workflow
 * (conventionally `.github/workflows/tia-baseline.yml`) runs the full
 * suite under `--tia` and uploads the `.pest/tia/` directory as a named
 * artifact (`pest-tia-baseline`) containing `graph.json` +
 * `coverage.bin`. On dev
 * machines, this class finds the latest successful run of that workflow
 * and downloads the artifact via `gh`.
 *
 * Why artifacts, not releases:
 *   - No tag is created → no `push` event cascade into CI workflows.
 *   - No release event → no deploy workflows tied to `release:published`.
 *   - Retention is run-scoped and tunable (1-90 days) instead of clobbering
 *     a single floating tag.
 *   - Publishing is strictly CI-only: artifacts can't be produced from a
 *     developer's laptop. This enforces the "CI is the authoritative
 *     publisher" policy that local-publish paths would otherwise erode.
 *
 * Fingerprint validation happens back in `Tia::handleParent` after the
 * blobs are written: a mismatched environment (different PHP version,
 * composer.lock, etc.) discards the pulled baseline and falls through to
 * the regular record path.
 *
 * @internal
 */
final readonly class BaselineSync
{
    /**
     * Conventional workflow filename teams publish from. Not configurable
     * for MVP — teams that outgrow the default can set
     * `PEST_TIA_BASELINE_WORKFLOW` later.
     */
    private const string WORKFLOW_FILE = 'tia-baseline.yml';

    /**
     * Artifact name the workflow uploads under. The artifact is a zip
     * containing `graph.json` (always) + `coverage.bin` (optional).
     */
    private const string ARTIFACT_NAME = 'pest-tia-baseline';

    /**
     * Asset filenames inside the artifact — mirror the state keys so the
     * CI publisher and the sync consumer stay in lock-step.
     */
    private const string GRAPH_ASSET = Tia::KEY_GRAPH;

    private const string COVERAGE_ASSET = Tia::KEY_COVERAGE_CACHE;

    /**
     * Cooldown (in seconds) applied after a failed baseline fetch.
     * Rationale: when the remote workflow hasn't published yet, every
     * `pest --tia` invocation would otherwise re-hit `gh run list` and
     * re-print the publish instructions — noisy + slow. Back off for a
     * day, let the user override with `--refetch`.
     */
    private const int FETCH_COOLDOWN_SECONDS = 86400;

    public function __construct(
        private State $state,
        private OutputInterface $output,
    ) {}

    /**
     * Detects the repo, fetches the latest baseline artifact, writes its
     * contents into the TIA state store. Returns true when the graph blob
     * landed; coverage is best-effort since plain `--tia` (no `--coverage`)
     * never reads it.
     *
     * `$force = true` (driven by `--refetch`) ignores the post-failure
     * cooldown so the user can retry on demand without waiting out the
     * 24h window.
     */
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

        $this->output->writeln(sprintf(
            '  <fg=cyan>TIA</> fetching baseline from <fg=white>%s</>…',
            $repo,
        ));

        $payload = $this->download($repo);

        if ($payload === null) {
            $this->startCooldown();
            $this->emitPublishInstructions($repo);

            return false;
        }

        if (! $this->state->write(Tia::KEY_GRAPH, $payload['graph'])) {
            return false;
        }

        if ($payload['coverage'] !== null) {
            $this->state->write(Tia::KEY_COVERAGE_CACHE, $payload['coverage']);
        }

        // Successful fetch wipes any stale cooldown so the next failure
        // (say, weeks later) starts a fresh 24h timer rather than inheriting
        // one from the deep past.
        $this->clearCooldown();

        $this->output->writeln(sprintf(
            '  <fg=green>TIA</> baseline ready (%s).',
            $this->formatSize(strlen($payload['graph']) + strlen($payload['coverage'] ?? '')),
        ));

        return true;
    }

    /**
     * Seconds left on the cooldown, or `null` when the cooldown is cleared
     * / expired / unreadable.
     */
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

    /**
     * Prints actionable instructions for publishing a first baseline when
     * the consumer-side fetch finds nothing.
     *
     * Behaviour splits on environment:
     *   - **CI:** a single line. The current run is almost certainly *the*
     *     publisher (it's what this workflow does by definition), so
     *     printing the whole recipe again is redundant and noisy.
     *   - **Local:** the full recipe, adapted to Laravel's pre-test steps
     *     (`.env.example` copy + `artisan key:generate`) when the framework
     *     is present. Generic PHP projects get a slimmer skeleton.
     */
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

    /**
     * True when running inside a CI provider. Conservative list — only the
     * three providers Pest formally supports / sees in the wild. `CI=true`
     * alone is ambiguous (users set it locally too) so we require a
     * provider-specific flag.
     */
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

    /**
     * Laravel projects need a populated `.env` and a generated `APP_KEY`
     * before the first boot, otherwise `Illuminate\Encryption\MissingAppKeyException`
     * fires during `setUp`. Include the standard pre-test dance plus the
     * extension set typical Laravel apps rely on.
     */
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
     * Two-step fetch: find the latest successful run of the baseline
     * workflow, then download the named artifact from it. Returns
     * `['graph' => bytes, 'coverage' => bytes|null]` on success, or null
     * if `gh` is unavailable, the workflow hasn't run yet, the artifact
     * is missing, or any shell step fails.
     *
     * @return array{graph: string, coverage: ?string}|null
     */
    private function download(string $repo): ?array
    {
        if (! $this->commandExists('gh')) {
            return null;
        }

        $runId = $this->latestSuccessfulRunId($repo);

        if ($runId === null) {
            return null;
        }

        $tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-tia-'.bin2hex(random_bytes(4));

        if (! @mkdir($tmpDir, 0755, true) && ! is_dir($tmpDir)) {
            return null;
        }

        $process = new Process([
            'gh', 'run', 'download', $runId,
            '-R', $repo,
            '-n', self::ARTIFACT_NAME,
            '-D', $tmpDir,
        ]);
        $process->setTimeout(120.0);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->cleanup($tmpDir);

            return null;
        }

        $graphPath = $tmpDir.DIRECTORY_SEPARATOR.self::GRAPH_ASSET;
        $coveragePath = $tmpDir.DIRECTORY_SEPARATOR.self::COVERAGE_ASSET;

        $graph = is_file($graphPath) ? @file_get_contents($graphPath) : false;

        if ($graph === false) {
            $this->cleanup($tmpDir);

            return null;
        }

        $coverage = is_file($coveragePath) ? @file_get_contents($coveragePath) : false;

        $this->cleanup($tmpDir);

        return [
            'graph' => $graph,
            'coverage' => $coverage === false ? null : $coverage,
        ];
    }

    /**
     * Queries GitHub for the most recent successful run of the baseline
     * workflow. `--jq '.[0].databaseId // empty'` coerces "no runs found"
     * into an empty string, which we map to null.
     */
    private function latestSuccessfulRunId(string $repo): ?string
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
            return null;
        }

        $runId = trim($process->getOutput());

        return $runId === '' ? null : $runId;
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
