<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Composer\InstalledVersions;
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

    // Project-local directory where artifacts from previous downloads are
    // kept (one subfolder per workflow run id). Hitting the same run id on
    // a later fetch skips the `gh run download` round trip entirely —
    // artifacts are immutable per run id, so the cached bytes are exactly
    // what gh would re-download.
    private const string DOWNLOAD_CACHE_REL_DIR = '.pest/artifacts';

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

        $this->output->writeln(sprintf(
            '  <fg=cyan>TIA</> fetching baseline from <fg=white>%s</>…',
            $repo,
        ));

        $payload = $this->download($repo, $projectRoot);

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

    /** @return array{graph: string, coverage: ?string}|null */
    private function download(string $repo, string $projectRoot): ?array
    {
        if (! $this->commandExists('gh')) {
            return null;
        }

        $runId = $this->latestSuccessfulRunId($repo);

        if ($runId === null) {
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

            return $this->readArtifact($runCacheDir);
        }

        if (! @mkdir($runCacheDir, 0755, true) && ! is_dir($runCacheDir)) {
            return null;
        }

        $process = new Process([
            'gh', 'run', 'download', $runId,
            '-R', $repo,
            '-n', self::ARTIFACT_NAME,
            '-D', $runCacheDir,
        ]);
        $process->setTimeout(300.0);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->cleanup($runCacheDir);

            return null;
        }

        $payload = $this->readArtifact($runCacheDir);

        if ($payload === null) {
            $this->cleanup($runCacheDir);

            return null;
        }

        $this->trimDownloadCache($projectRoot);

        return $payload;
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
        return rtrim($projectRoot, '/\\')
            .DIRECTORY_SEPARATOR
            .str_replace('/', DIRECTORY_SEPARATOR, self::DOWNLOAD_CACHE_REL_DIR);
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
