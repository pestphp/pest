<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Exceptions\BaselineFetchFailed;
use Pest\Panic;
use Pest\Plugins\Tia;
use Pest\Plugins\Tia\Baselines\BaseRemote;
use Pest\Plugins\Tia\Contracts\State;
use Pest\Support\View;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
final readonly class BaselineSync
{
    private const string GRAPH_ASSET = Tia::KEY_GRAPH;

    private const string COVERAGE_ASSET = Tia::KEY_COVERAGE_CACHE;

    private const string DOWNLOAD_CACHE_DIR = 'artifacts';

    private const int DOWNLOAD_CACHE_MAX_ENTRIES = 5;

    private const int FETCH_COOLDOWN_SECONDS = 86400;

    /**
     * @var array<int, class-string<BaseRemote>>
     */
    private const array REMOTES = [
        Baselines\GitHubRemote::class,
        Baselines\GitLabRemote::class,
    ];

    public function __construct(
        private State $state,
        private OutputInterface $output,
        private WatchPatterns $watchPatterns,
    ) {}

    private function renderBadge(string $type, string $content): void
    {
        View::render('components.badge', ['type' => $type, 'content' => $content]);
    }

    private function renderChild(string $text): void
    {
        $this->output->writeln(sprintf('  <fg=gray>─ %s</>', $text));
    }

    public function fetchIfAvailable(string $projectRoot, bool $force = false, bool $hasAnchor = false): bool
    {
        $detected = $this->detectRemote($projectRoot);

        if ($detected === null) {
            return false;
        }

        [$remote, $repo] = $detected;

        if (! $force && ($remaining = $this->cooldownRemaining()) !== null) {
            $this->renderBadge('WARN', sprintf(
                'Last fetch found no baseline — next auto-retry in %s. Override with --refetch.',
                $this->formatDuration($remaining),
            ));

            return false;
        }

        $result = $this->download($remote, $repo, $projectRoot, $hasAnchor);
        $payload = $result['payload'];
        $failureKind = $result['failureKind'];

        if ($payload === null) {
            if ($failureKind === 'no-runs' || $failureKind === null) {
                $this->startCooldown();
                $this->emitPublishInstructions();
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

        return true;
    }

    /**
     * @return array{0: BaseRemote, 1: string}|null
     */
    private function detectRemote(string $projectRoot): ?array
    {
        foreach (self::REMOTES as $class) {
            $remote = new $class($this->watchPatterns);
            $repo = $remote->detect($projectRoot);

            if ($repo !== null) {
                return [$remote, $repo];
            }
        }

        return null;
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

    private function emitPublishInstructions(): void
    {
        if ($this->isCi()) {
            $this->renderBadge('INFO', 'No baseline yet — this run will produce one.');

            return;
        }

        $this->renderBadge('WARN', 'No baseline published yet — recording locally.');
        $this->renderChild('See https://pestphp.com/docs/tia for how to publish one from CI.');
    }

    private function isCi(): bool
    {
        return getenv('GITHUB_ACTIONS') === 'true'
            || getenv('GITLAB_CI') === 'true'
            || getenv('CIRCLECI') === 'true';
    }

    /**
     * @return array{payload: array{graph: string, coverage: ?string, sizeOnDisk: int}|null, failureKind: ?string}
     */
    private function download(BaseRemote $remote, string $repo, string $projectRoot, bool $hasAnchor = false): array
    {
        $this->validateCliDependencies($remote, $hasAnchor);

        [$runId, $listError] = $remote->latestSuccessfulRunId($repo);

        if ($listError !== null) {
            $this->panicOnClassifiedError($remote, $listError, 'Failed to query baseline runs', $hasAnchor);

            $this->renderBadge('WARN', sprintf(
                'Failed to query baseline runs — %s',
                $listError['message'],
            ));

            return ['payload' => null, 'failureKind' => $listError['kind']];
        }

        if ($runId === null) {
            return ['payload' => null, 'failureKind' => 'no-runs'];
        }

        $runCacheDir = $this->downloadCacheDir($projectRoot).DIRECTORY_SEPARATOR.$this->safeRunId($runId);

        if (is_file($runCacheDir.DIRECTORY_SEPARATOR.self::GRAPH_ASSET)) {
            @touch($runCacheDir);

            $this->renderChild(sprintf(
                'Using cached baseline from %s (run %s).',
                $repo,
                $runId,
            ));

            return ['payload' => $this->readArtifact($runCacheDir), 'failureKind' => null];
        }

        if (! @mkdir($runCacheDir, 0755, true) && ! is_dir($runCacheDir)) {
            return ['payload' => null, 'failureKind' => null];
        }

        $download = $this->downloadArtifact($remote, $repo, $runId, $runCacheDir, $hasAnchor);

        if (! $download['success']) {
            return ['payload' => null, 'failureKind' => $download['failureKind']];
        }

        $payload = $this->validateDownloadedArtifact($runCacheDir, $hasAnchor);

        $this->trimDownloadCache($projectRoot);

        return ['payload' => $payload, 'failureKind' => null];
    }

    /**
     * @param  array{kind: string, message: string}  $diagnosis
     */
    private function panicOnClassifiedError(BaseRemote $remote, array $diagnosis, string $contextPrefix, bool $hasAnchor): void
    {
        if (! in_array($diagnosis['kind'], ['forbidden', 'not-found'], true)) {
            return;
        }

        Panic::with(new BaselineFetchFailed(
            sprintf('%s — %s', $contextPrefix, $diagnosis['message']),
            sprintf('Verify your CI baseline configuration and `%s` token scope.', $remote->cliName()),
            $hasAnchor,
        ));
    }

    private function validateCliDependencies(BaseRemote $remote, bool $hasAnchor): void
    {
        if (! $remote->cliExists()) {
            Panic::with(new BaselineFetchFailed(
                sprintf('%s CLI (%s) not found — cannot fetch baseline.', $remote->providerLabel(), $remote->cliName()),
                sprintf('Install it from %s.', $remote->installUrl()),
                $hasAnchor,
            ));
        }

        if (! $remote->cliAuthenticated()) {
            Panic::with(new BaselineFetchFailed(
                sprintf('%s CLI (%s) is not authenticated — cannot fetch baseline.', $remote->providerLabel(), $remote->cliName()),
                sprintf('Run `%s` and retry.', $remote->loginCommand()),
                $hasAnchor,
            ));
        }
    }

    /**
     * @return array{success: bool, failureKind: ?string}
     */
    private function downloadArtifact(BaseRemote $remote, string $repo, string $runId, string $runCacheDir, bool $hasAnchor): array
    {
        $artifactSize = $remote->artifactSize($repo, $runId);

        $this->output->writeln('');
        $this->renderChild($artifactSize !== null
            ? sprintf(
                'Downloading TIA baseline (%s) from %s…',
                $this->formatSize($artifactSize),
                $repo,
            )
            : sprintf(
                'Downloading TIA baseline from %s…',
                $repo,
            ));

        $process = new Process($remote->downloadCommand($repo, $runId, $runCacheDir));
        $process->setTimeout(900.0);
        $process->start();

        $startedAt = microtime(true);
        $tick = 0;

        while ($process->isRunning()) {
            $this->renderDownloadProgress($startedAt, $tick++);
            usleep(120_000);
        }

        $process->wait();
        $this->clearProgressLine();

        if ($process->isSuccessful()) {
            return ['success' => true, 'failureKind' => null];
        }

        $this->cleanup($runCacheDir);

        $diagnosis = $remote->classifyError($process->getErrorOutput().$process->getOutput());

        $this->panicOnClassifiedError($remote, $diagnosis, 'Baseline download failed', $hasAnchor);

        $this->renderBadge('WARN', sprintf(
            'Baseline download failed — %s',
            $diagnosis['message'],
        ));

        return ['success' => false, 'failureKind' => $diagnosis['kind']];
    }

    /**
     * @return array{graph: string, coverage: ?string, sizeOnDisk: int}
     */
    private function validateDownloadedArtifact(string $runCacheDir, bool $hasAnchor): array
    {
        $payload = $this->readArtifact($runCacheDir);

        if ($payload === null) {
            $this->cleanup($runCacheDir);

            Panic::with(new BaselineFetchFailed(
                'Baseline downloaded but the artifact is missing expected files (graph.json).',
                'Your CI publish step is broken — check the job that uploads the TIA baseline artifact.',
                $hasAnchor,
            ));
        }

        return $payload;
    }

    private function renderDownloadProgress(float $startedAt, int $tick): void
    {
        static $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

        $elapsed = max(0.0, microtime(true) - $startedAt);
        $frame = $frames[$tick % count($frames)];

        $this->output->write(sprintf(
            "\r\033[K    <fg=gray>%s %.1fs elapsed</>",
            $frame,
            $elapsed,
        ));
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
     * @return array{graph: string, coverage: ?string, sizeOnDisk: int}|null
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
            'sizeOnDisk' => $this->dirSize($dir),
        ];
    }

    private function downloadCacheDir(string $projectRoot): string
    {
        return Storage::tempDir($projectRoot).DIRECTORY_SEPARATOR.self::DOWNLOAD_CACHE_DIR;
    }

    private function safeRunId(string $runId): string
    {
        $sanitised = preg_replace('/[^A-Za-z0-9_-]/', '', $runId) ?? '';

        return $sanitised === '' ? 'unknown' : $sanitised;
    }

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
            if (in_array($entry, ['.', '..'], true)) {
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

    private function cleanup(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var \SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if ($entry->isDir()) {
                @rmdir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
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
