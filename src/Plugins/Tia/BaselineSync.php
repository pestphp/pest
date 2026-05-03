<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Exceptions\BaselineFetchFailed;
use Pest\Panic;
use Pest\Plugins\Tia;
use Pest\Plugins\Tia\Contracts\State;
use Pest\Support\View;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
final readonly class BaselineSync
{
    private const string WORKFLOW_FILE = 'tia-baseline.yml';

    private const string ARTIFACT_NAME = 'pest-tia-baseline';

    private const string GRAPH_ASSET = Tia::KEY_GRAPH;

    private const string COVERAGE_ASSET = Tia::KEY_COVERAGE_CACHE;

    private const string DOWNLOAD_CACHE_DIR = 'artifacts';

    private const int DOWNLOAD_CACHE_MAX_ENTRIES = 5;

    private const int FETCH_COOLDOWN_SECONDS = 86400;

    private const array DIAGNOSES = [
        'network' => [
            'pattern' => '/could not resolve host|connection refused|connection reset|temporary failure in name resolution|network is unreachable|no route to host|i\/o timeout|tls handshake|getaddrinfo/i',
            'message' => 'network error (offline or DNS unreachable). Try again when connected.',
        ],
        'gh-auth' => [
            'pattern' => '/authentication failed|not logged in|requires authentication|bad credentials|401/i',
            'message' => 'authentication failed — run `gh auth login` and retry.',
        ],
        'rate-limit' => [
            'pattern' => '/rate limit|too many requests|secondary rate limit/i',
            'message' => 'GitHub API rate limit hit — try again later.',
        ],
        'not-found' => [
            'pattern' => '/404|not found|repository not found/i',
            'message' => 'workflow or artifact not found in repo.',
        ],
        'forbidden' => [
            'pattern' => '/403|forbidden|access denied/i',
            'message' => 'access denied — check that your `gh` token has repo + actions read scope.',
        ],
    ];

    public function __construct(
        private State $state,
        private OutputInterface $output,
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
        $repo = $this->detectGitHubRepo($projectRoot);

        if ($repo === null) {
            return false;
        }

        if (! $force && ($remaining = $this->cooldownRemaining()) !== null) {
            $this->renderBadge('WARN', sprintf(
                'Last fetch found no baseline — next auto-retry in %s. Override with --refetch.',
                $this->formatDuration($remaining),
            ));

            return false;
        }

        $result = $this->download($repo, $projectRoot, $hasAnchor);
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
     * @return array{payload: array{graph: string, coverage: ?string, sizeOnDisk: int}|null, failureKind: ?string}
     */
    private function download(string $repo, string $projectRoot, bool $hasAnchor = false): array
    {
        $this->validateGhDependencies($hasAnchor);

        [$runId, $listError] = $this->latestSuccessfulRunIdWithError($repo);

        if ($listError !== null) {
            $this->panicOnClassifiedError($listError, 'Failed to query baseline runs', $hasAnchor);

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

        $download = $this->downloadArtifact($repo, $runId, $runCacheDir, $hasAnchor);

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
    private function panicOnClassifiedError(array $diagnosis, string $contextPrefix, bool $hasAnchor): void
    {
        if (! in_array($diagnosis['kind'], ['forbidden', 'not-found'], true)) {
            return;
        }

        Panic::with(new BaselineFetchFailed(
            sprintf('%s — %s', $contextPrefix, $diagnosis['message']),
            'Verify workflow tia-baseline.yml, artifact pest-tia-baseline, and gh token scope.',
            $hasAnchor,
        ));
    }

    private function validateGhDependencies(bool $hasAnchor): void
    {
        if (! $this->commandExists('gh')) {
            Panic::with(new BaselineFetchFailed(
                'GitHub CLI (gh) not found — cannot fetch baseline.',
                'Install it from https://cli.github.com.',
                $hasAnchor,
            ));
        }

        if (! $this->ghAuthenticated()) {
            Panic::with(new BaselineFetchFailed(
                'GitHub CLI (gh) is not authenticated — cannot fetch baseline.',
                'Run `gh auth login` and retry.',
                $hasAnchor,
            ));
        }
    }

    /**
     * @return array{success: bool, failureKind: ?string}
     */
    private function downloadArtifact(string $repo, string $runId, string $runCacheDir, bool $hasAnchor): array
    {
        $artifactSize = $this->artifactSize($repo, $runId);

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

        $process = new Process([
            'gh', 'run', 'download', $runId,
            '-R', $repo,
            '-n', self::ARTIFACT_NAME,
            '-D', $runCacheDir,
        ]);
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

        $diagnosis = $this->classifyGhError($process->getErrorOutput().$process->getOutput());

        $this->panicOnClassifiedError($diagnosis, 'Baseline download failed', $hasAnchor);

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
                'Your CI publish step is broken — check the workflow that uploads pest-tia-baseline.',
                $hasAnchor,
            ));
        }

        return $payload;
    }

    private function artifactSize(string $repo, string $runId): ?int
    {
        $process = new Process([
            'gh', 'api',
            sprintf('repos/%s/actions/runs/%s/artifacts', $repo, $runId),
            '--jq', sprintf(
                '.artifacts[] | select(.name == "%s") | .size_in_bytes', // @pest-ignore-type
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

    /**
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
     * @return array{kind: string, message: string}
     */
    private function classifyGhError(string $output): array
    {
        $output = trim($output);

        if ($output === '') {
            return ['kind' => 'unknown', 'message' => 'unknown error'];
        }

        foreach (self::DIAGNOSES as $kind => $diagnosis) {
            if (preg_match($diagnosis['pattern'], $output) === 1) {
                return ['kind' => $kind, 'message' => $diagnosis['message']];
            }
        }

        return ['kind' => 'unknown', 'message' => trim(strtok($output, "\n"))];
    }

    private function commandExists(string $cmd): bool
    {
        $process = new Process(['which', $cmd]);
        $process->run();

        return $process->isSuccessful();
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
