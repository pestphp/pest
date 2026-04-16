<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\BeforeEachable;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Contracts\Plugins\Terminable;
use Pest\Plugins\Tia\CachedTestResult;
use Pest\Plugins\Tia\ChangedFiles;
use Pest\Plugins\Tia\Fingerprint;
use Pest\Plugins\Tia\Graph;
use Pest\Plugins\Tia\Recorder;
use Pest\Plugins\Tia\ResultCollector;
use Pest\TestCaseFilters\TiaTestCaseFilter;
use Pest\Plugins\Tia\WatchPatterns;
use Pest\Support\Container;
use Pest\TestSuite;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Test Impact Analysis (file-level, parallel-aware).
 *
 * Modes
 * -----
 *  - **Record** — no graph (or fingerprint / recording commit drifted). The
 *    full suite runs with PCOV / Xdebug capture per test; the resulting
 *    `test → [source_file, …]` edges land in `.temp/tia.json`.
 *  - **Replay** — graph valid. We diff the working tree against the recording
 *    commit, intersect changed files with graph edges, and run only the
 *    affected tests. Newly-added tests unknown to the graph are always
 *    accepted (skipping them would be a correctness hazard).
 *
 * Parallel integration
 * --------------------
 * This plugin MUST run before `Pest\Plugins\Parallel` in the registered
 * plugin list — Parallel exits the process as soon as it sees `--parallel`,
 * so later plugins never get their turn. With the correct order:
 *
 *  - **Parent, replay**: narrow the CLI args down to the affected test
 *    files before Parallel hands them to paratest. Workers then only see
 *    the narrowed file set and nothing special is required of them.
 *  - **Parent, record**: flip a global recording flag (via
 *    `Parallel::setGlobal`) so every spawned worker activates its own
 *    coverage recorder. The parent does not itself record (paratest runs
 *    tests in workers); instead we register an `AddsOutput` hook that
 *    merges per-worker partial graphs after paratest finishes.
 *  - **Worker, record**: boots through `bin/worker.php`, which re-runs
 *    `CallsHandleArguments`. We detect the worker context + recording flag,
 *    activate the `Recorder`, and flush the partial graph on `terminate()`
 *    into `.temp/tia-worker-<TEST_TOKEN>.json`.
 *  - **Worker, replay**: nothing to do; args already narrowed.
 *
 * Guardrails
 * ----------
 *  - `--tia` combined with `--coverage` is refused: both paths drive the
 *    same coverage driver and would corrupt each other's data.
 *  - If no coverage driver is available during record, we skip gracefully;
 *    the suite still runs normally.
 *  - A stale recording SHA (rebase / force-push) triggers a rebuild.
 *
 * @internal
 */
final class Tia implements AddsOutput, BeforeEachable, HandlesArguments, Terminable
{
    use Concerns\HandleArguments;

    private const string OPTION = '--tia';

    private const string REBUILD_OPTION = '--tia-rebuild';

    /**
     * TIA cache lives inside Pest's `.temp/` directory (same location as
     * PHPUnit's result cache). This directory is gitignored by default in
     * Pest's own `.gitignore`, so the graph is never committed.
     */
    private const string TEMP_DIR = __DIR__
        .DIRECTORY_SEPARATOR.'..'
        .DIRECTORY_SEPARATOR.'..'
        .DIRECTORY_SEPARATOR.'.temp';

    private const string CACHE_FILE = 'tia.json';

    private const string AFFECTED_FILE = 'tia-affected.json';

    private const string WORKER_PREFIX = 'tia-worker-';

    /**
     * Global flag toggled by the parent process so workers know to record.
     */
    private const string RECORDING_GLOBAL = 'TIA_RECORDING';

    /**
     * Global flag that tells workers to install the TIA filter (replay mode).
     * Workers read the affected set from `.temp/tia-affected.json`.
     */
    private const string REPLAYING_GLOBAL = 'TIA_REPLAYING';

    private bool $graphWritten = false;

    private bool $replayRan = false;

    /**
     * Holds the graph during replay so `beforeEach` can look up cached
     * results without re-loading from disk on every test.
     */
    private ?Graph $replayGraph = null;

    /**
     * Current git branch (or `HEAD` SHA when detached). Resolved once per
     * run so all graph accesses use the same branch key.
     */
    private string $branch = 'main';

    /**
     * Test files that are affected (should re-execute). Keyed by
     * project-relative path. Set during `enterReplayMode`.
     *
     * @var array<string, true>
     */
    private array $affectedFiles = [];

    private static function tempDir(): string
    {
        $dir = (string) realpath(self::TEMP_DIR);

        if ($dir === '' || $dir === '.') {
            // .temp doesn't exist yet — create it.
            @mkdir(self::TEMP_DIR, 0755, true);
            $dir = (string) realpath(self::TEMP_DIR);
        }

        return $dir;
    }

    private static function cachePath(): string
    {
        return self::tempDir().DIRECTORY_SEPARATOR.self::CACHE_FILE;
    }

    private static function affectedPath(): string
    {
        return self::tempDir().DIRECTORY_SEPARATOR.self::AFFECTED_FILE;
    }

    private static function workerPath(string $token): string
    {
        return self::tempDir().DIRECTORY_SEPARATOR.self::WORKER_PREFIX.$token.'.json';
    }

    private static function workerGlob(): string
    {
        return self::tempDir().DIRECTORY_SEPARATOR.self::WORKER_PREFIX.'*.json';
    }

    public function __construct(
        private readonly OutputInterface $output,
        private readonly Recorder $recorder,
        private readonly WatchPatterns $watchPatterns,
    ) {}

    public function beforeEach(string $filename, string $testId): ?CachedTestResult
    {
        if ($this->replayGraph === null) {
            return null;
        }

        // Resolve file to project-relative path.
        $projectRoot = TestSuite::getInstance()->rootPath;
        $real = @realpath($filename);
        $rel = $real !== false
            ? str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen(rtrim($projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)))
            : null;

        // Affected files must re-execute.
        if ($rel !== null && isset($this->affectedFiles[$rel])) {
            return null;
        }

        // Unknown files (not in graph) must execute — they're new.
        if ($rel === null || ! $this->replayGraph->knowsTest($rel)) {
            return null;
        }

        // Known + unaffected: return cached result if we have one for this
        // branch (falls back to main if branch is fresh).
        return $this->replayGraph->getResult($this->branch, $testId);
    }

    /**
     * {@inheritDoc}
     */
    public function handleArguments(array $arguments): array
    {
        $isWorker = Parallel::isWorker();
        $recordingGlobal = $isWorker && (string) Parallel::getGlobal(self::RECORDING_GLOBAL) === '1';
        $replayingGlobal = $isWorker && (string) Parallel::getGlobal(self::REPLAYING_GLOBAL) === '1';

        $enabled = $this->hasArgument(self::OPTION, $arguments);
        $forceRebuild = $this->hasArgument(self::REBUILD_OPTION, $arguments);

        if (! $enabled && ! $forceRebuild && ! $recordingGlobal && ! $replayingGlobal) {
            return $arguments;
        }

        $arguments = $this->popArgument(self::OPTION, $arguments);
        $arguments = $this->popArgument(self::REBUILD_OPTION, $arguments);

        if ($this->coverageReportActive()) {
            if (! $isWorker) {
                $this->output->writeln(
                    '  <fg=yellow>TIA</> `--coverage` is active — TIA disabled to avoid '.
                    'conflicting with PHPUnit\'s own coverage collection.',
                );
            }

            return $arguments;
        }

        $projectRoot = TestSuite::getInstance()->rootPath;

        if ($isWorker) {
            return $this->handleWorker($arguments, $projectRoot, $recordingGlobal, $replayingGlobal);
        }

        return $this->handleParent($arguments, $projectRoot, $forceRebuild);
    }

    public function terminate(): void
    {
        if ($this->graphWritten) {
            return;
        }

        $recorder = $this->recorder;

        if (! $recorder->isActive()) {
            return;
        }

        $this->graphWritten = true;

        $projectRoot = TestSuite::getInstance()->rootPath;
        $perTest = $recorder->perTestFiles();

        if ($perTest === []) {
            $recorder->reset();

            return;
        }

        if (Parallel::isWorker()) {
            $this->flushWorkerPartial($projectRoot, $perTest);
            $recorder->reset();

            return;
        }

        // Non-parallel record path: straight into the main cache.
        $cachePath = self::cachePath();

        $graph = Graph::load($projectRoot, $cachePath) ?? new Graph($projectRoot);
        $graph->setFingerprint(Fingerprint::compute($projectRoot));
        $graph->setRecordedAtSha($this->branch, (new ChangedFiles($projectRoot))->currentSha());
        $graph->replaceEdges($perTest);
        $graph->pruneMissingTests();

        if (! $graph->save($cachePath)) {
            $this->output->writeln('  <fg=red>TIA</> failed to write graph to '.$cachePath);
            $recorder->reset();

            return;
        }

        $this->output->writeln(sprintf(
            '  <fg=green>TIA</> graph recorded (%d test files) at %s',
            count($perTest),
            self::CACHE_FILE,
        ));

        $recorder->reset();
    }

    /**
     * Runs after paratest finishes in the parent process. If we were
     * recording across workers, merge their partial graphs into the main
     * cache now.
     */
    public function addOutput(int $exitCode): int
    {
        if (Parallel::isWorker()) {
            return $exitCode;
        }

        // After a successful replay run, advance the recorded SHA to HEAD
        // so the next run only diffs against what changed since NOW, not
        // since the original recording. Without this, re-running `--tia`
        // twice in a row would re-execute the same affected tests both
        // times even though nothing new changed.
        if ($this->replayRan) {
            $this->bumpRecordedSha();
        }

        // Snapshot per-test results (status + message) from PHPUnit's result
        // cache into our graph so future replay runs can faithfully reproduce
        // pass/fail/skip/todo/incomplete for unaffected tests.
        $this->snapshotTestResults();

        if ((string) Parallel::getGlobal(self::RECORDING_GLOBAL) !== '1') {
            return $exitCode;
        }

        $projectRoot = TestSuite::getInstance()->rootPath;
        $partials = $this->collectWorkerPartials($projectRoot);

        if ($partials === []) {
            return $exitCode;
        }

        $cachePath = self::cachePath();

        $graph = Graph::load($projectRoot, $cachePath) ?? new Graph($projectRoot);
        $graph->setFingerprint(Fingerprint::compute($projectRoot));
        $graph->setRecordedAtSha($this->branch, (new ChangedFiles($projectRoot))->currentSha());

        $merged = [];

        foreach ($partials as $partialPath) {
            $data = $this->readPartial($partialPath);

            if ($data === null) {
                continue;
            }

            foreach ($data as $testFile => $sources) {
                if (! isset($merged[$testFile])) {
                    $merged[$testFile] = [];
                }

                foreach ($sources as $source) {
                    $merged[$testFile][$source] = true;
                }
            }

            @unlink($partialPath);
        }

        $finalised = [];

        foreach ($merged as $testFile => $sourceSet) {
            $finalised[$testFile] = array_keys($sourceSet);
        }

        $graph->replaceEdges($finalised);
        $graph->pruneMissingTests();

        if (! $graph->save($cachePath)) {
            $this->output->writeln('  <fg=red>TIA</> failed to write graph to '.$cachePath);

            return $exitCode;
        }

        $this->output->writeln(sprintf(
            '  <fg=green>TIA</> graph recorded (%d test files, %d worker partials) at %s',
            count($finalised),
            count($partials),
            self::CACHE_FILE,
        ));

        return $exitCode;
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function handleParent(array $arguments, string $projectRoot, bool $forceRebuild): array
    {
        // Initialise watch patterns (defaults + any user additions from
        // tests/Pest.php which has already been loaded by BootFiles at
        // this point).
        $this->watchPatterns->useDefaults($projectRoot);

        // Resolve current branch once per run so every baseline lookup uses
        // the same key. Detached HEAD (or no git) falls back to `main` as
        // the implicit branch identity.
        $this->branch = (new ChangedFiles($projectRoot))->currentBranch() ?? 'main';

        $cachePath = self::cachePath();
        $fingerprint = Fingerprint::compute($projectRoot);

        $graph = $forceRebuild ? null : Graph::load($projectRoot, $cachePath);

        if ($graph instanceof Graph && ! Fingerprint::matches($graph->fingerprint(), $fingerprint)) {
            $this->output->writeln(
                '  <fg=yellow>TIA</> environment fingerprint changed — graph will be rebuilt.',
            );
            $graph = null;
        }

        if ($graph instanceof Graph) {
            $changedFiles = new ChangedFiles($projectRoot);
            $branchSha = $graph->recordedAtSha($this->branch);

            if ($changedFiles->gitAvailable()
                && $branchSha !== null
                && $changedFiles->since($branchSha) === null) {
                $this->output->writeln(
                    '  <fg=yellow>TIA</> recorded commit is no longer reachable — graph will be rebuilt.',
                );
                $graph = null;
            }
        }

        if ($graph instanceof Graph) {
            return $this->enterReplayMode($graph, $projectRoot, $arguments);
        }

        return $this->enterRecordMode($projectRoot, $arguments);
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function handleWorker(array $arguments, string $projectRoot, bool $recordingGlobal, bool $replayingGlobal): array
    {
        $this->branch = (new ChangedFiles($projectRoot))->currentBranch() ?? 'main';

        if ($replayingGlobal) {
            // Replay in a worker: load the graph and the affected set that
            // the parent persisted, then install the per-file filter so
            // whichever tests paratest happens to hand this worker are
            // accepted / rejected consistently with the series path.
            $this->installWorkerReplayFilter($projectRoot);

            return $arguments;
        }

        if (! $recordingGlobal) {
            return $arguments;
        }

        $recorder = $this->recorder;

        if (! $recorder->driverAvailable()) {
            // Driver availability is per-process. If the driver is missing
            // here, silently skip — the parent has already warned during
            // its own boot.
            return $arguments;
        }

        $recorder->activate();

        return $arguments;
    }

    private function installWorkerReplayFilter(string $projectRoot): void
    {
        $cachePath = self::cachePath();
        $affectedPath = self::affectedPath();

        $graph = Graph::load($projectRoot, $cachePath);

        if (! $graph instanceof Graph) {
            return;
        }

        $raw = @file_get_contents($affectedPath);

        if ($raw === false) {
            return;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return;
        }

        $affectedSet = [];

        foreach ($decoded as $rel) {
            if (is_string($rel)) {
                $affectedSet[$rel] = true;
            }
        }

        TestSuite::getInstance()->tests->addTestCaseFilter(
            new TiaTestCaseFilter($projectRoot, $graph, $affectedSet),
        );
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function enterReplayMode(Graph $graph, string $projectRoot, array $arguments): array
    {
        $changedFiles = new ChangedFiles($projectRoot);

        if (! $changedFiles->gitAvailable()) {
            $this->output->writeln(
                '  <fg=yellow>TIA</> git unavailable — running full suite.',
            );

            return $arguments;
        }

        $changed = $changedFiles->since($graph->recordedAtSha($this->branch)) ?? [];

        // Drop files whose content hash matches the last-run snapshot. This
        // is the "dirty but identical" filter: if a file is uncommitted but
        // its content hasn't moved since the last `--tia` invocation, its
        // dependents already re-ran last time and don't need re-running
        // again.
        $changed = $changedFiles->filterUnchangedSinceLastRun($changed, $graph->lastRunTree($this->branch));

        $affected = $changed === [] ? [] : $graph->affected($changed);

        $totalKnown = count($graph->allTestFiles());
        $affectedCount = count($affected);
        $cachedCount = $totalKnown - $affectedCount;

        $testSuite = TestSuite::getInstance();
        $affectedSet = array_fill_keys($affected, true);

        $this->replayRan = true;
        $this->replayGraph = $graph;
        $this->affectedFiles = $affectedSet;

        if (! Parallel::isEnabled()) {
            $this->output->writeln(sprintf(
                '  <fg=green>TIA</> %d changed file(s) → %d affected, %d replayed.',
                count($changed),
                $affectedCount,
                $cachedCount,
            ));

            return $arguments;
        }

        // Parallel: persist affected set so workers can install the filter.
        if (! $this->persistAffectedSet($projectRoot, $affected)) {
            $this->output->writeln(
                '  <fg=red>TIA</> failed to persist affected set — running full suite.',
            );

            return $arguments;
        }

        Parallel::setGlobal(self::REPLAYING_GLOBAL, '1');

        $this->output->writeln(sprintf(
            '  <fg=green>TIA</> %d changed file(s) → %d affected, %d cached (parallel).',
            count($changed),
            $affectedCount,
            $cachedCount,
        ));

        return $arguments;
    }

    /**
     * @param  array<int, string>  $affected  Project-relative paths.
     */
    private function persistAffectedSet(string $projectRoot, array $affected): bool
    {
        $path = self::affectedPath();
        $dir = dirname($path);

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return false;
        }

        $json = json_encode(array_values($affected), JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return false;
        }

        $tmp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        if (@file_put_contents($tmp, $json) === false) {
            return false;
        }

        if (! @rename($tmp, $path)) {
            @unlink($tmp);

            return false;
        }

        return true;
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function enterRecordMode(string $projectRoot, array $arguments): array
    {
        if (Parallel::isEnabled()) {
            // Parent driving `--parallel`: workers will do the actual
            // recording. We only advertise the intent through a global.
            // Clean up any stale partial files from a previous interrupted
            // run so the merge step doesn't confuse itself.
            $this->purgeWorkerPartials($projectRoot);

            Parallel::setGlobal(self::RECORDING_GLOBAL, '1');

            $this->output->writeln(
                '  <fg=cyan>TIA</> recording dependency graph in parallel (first run) — '.
                'subsequent `--tia` runs will only re-execute affected tests.',
            );

            return $arguments;
        }

        $recorder = $this->recorder;

        if (! $recorder->driverAvailable()) {
            $this->output->writeln([
                '',
                '  <fg=white;options=bold;bg=red> ERROR </> No coverage driver is available.',
                '',
                '  TIA requires ext-pcov or Xdebug with coverage mode enabled to',
                '  record the dependency graph. Install one and rerun with `--tia`.',
                '',
            ]);

            exit(1);
        }

        $recorder->activate();

        $this->output->writeln(sprintf(
            '  <fg=cyan>TIA</> recording dependency graph via %s (first run) — '.
            'subsequent `--tia` runs will only re-execute affected tests.',
            $recorder->driver(),
        ));

        return $arguments;
    }

    /**
     * @param  array<string, array<int, string>>  $perTest
     */
    private function flushWorkerPartial(string $projectRoot, array $perTest): void
    {
        $token = $_SERVER['TEST_TOKEN'] ?? $_ENV['TEST_TOKEN'] ?? getmypid();
        // Defensive: token might arrive as int or string depending on paratest
        // version. Cast + filter to keep filenames sane.
        $token = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $token);

        if ($token === '') {
            $token = (string) getmypid();
        }

        $path = self::workerPath($token);
        $dir = dirname($path);

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return;
        }

        $json = json_encode($perTest, JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return;
        }

        $tmp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        if (@file_put_contents($tmp, $json) === false) {
            return;
        }

        if (! @rename($tmp, $path)) {
            @unlink($tmp);
        }
    }

    /**
     * @return array<int, string>
     */
    private function collectWorkerPartials(string $projectRoot): array
    {
        $pattern = self::workerGlob();
        $matches = glob($pattern);

        return $matches === false ? [] : $matches;
    }

    private function purgeWorkerPartials(string $projectRoot): void
    {
        foreach ($this->collectWorkerPartials($projectRoot) as $path) {
            @unlink($path);
        }
    }

    /**
     * @return array<string, array<int, string>>|null
     */
    private function readPartial(string $path): ?array
    {
        $raw = @file_get_contents($path);

        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return null;
        }

        $out = [];

        foreach ($data as $test => $sources) {
            if (! is_string($test)) {
                continue;
            }
            if (! is_array($sources)) {
                continue;
            }
            $clean = [];

            foreach ($sources as $source) {
                if (is_string($source)) {
                    $clean[] = $source;
                }
            }

            $out[$test] = $clean;
        }

        return $out;
    }

    /**
     * After a successful replay, bump the graph's `recorded_at_sha` to the
     * current HEAD. This way the next `--tia` run diffs only against what
     * changed since THIS run, not since the original recording.
     *
     * The graph edges themselves are untouched — only the SHA marker moves.
     */
    /**
     * After a successful replay, advance the baseline: bump `recorded_at_sha`
     * to the current HEAD (handles committed changes) and snapshot the
     * working tree's content hashes (handles uncommitted changes). Next run
     * compares against this baseline so identical files are skipped even if
     * git still reports them as modified.
     */
    private function bumpRecordedSha(): void
    {
        $projectRoot = TestSuite::getInstance()->rootPath;
        $cachePath = self::cachePath();

        $graph = Graph::load($projectRoot, $cachePath);

        if (! $graph instanceof Graph) {
            return;
        }

        $changedFiles = new ChangedFiles($projectRoot);
        $currentSha = $changedFiles->currentSha();

        if ($currentSha !== null) {
            $graph->setRecordedAtSha($this->branch, $currentSha);
        }

        // Snapshot the working tree: hash every currently-modified file.
        // On next run, files still appearing as modified but whose hash
        // matches this snapshot are treated as unchanged.
        $workingTreeFiles = $changedFiles->since($currentSha) ?? [];
        $graph->setLastRunTree($this->branch, $changedFiles->snapshotTree($workingTreeFiles));

        $graph->save($cachePath);
    }

    /**
     * Merges per-test status + message from the `ResultCollector` into the
     * TIA graph. Runs after every `--tia` invocation so the graph always has
     * fresh results for faithful replay (pass, fail, skip, todo, etc.).
     */
    private function snapshotTestResults(): void
    {
        /** @var ResultCollector $collector */
        $collector = Container::getInstance()->get(ResultCollector::class);

        $results = $collector->all();

        if ($results === []) {
            return;
        }

        $cachePath = self::cachePath();
        $projectRoot = TestSuite::getInstance()->rootPath;

        $graph = Graph::load($projectRoot, $cachePath);

        if (! $graph instanceof Graph) {
            return;
        }

        foreach ($results as $testId => $result) {
            $graph->setResult($this->branch, $testId, $result['status'], $result['message'], $result['time']);
        }

        $graph->save($cachePath);
        $collector->reset();
    }

    private function coverageReportActive(): bool
    {
        try {
            /** @var Coverage $coverage */
            $coverage = Container::getInstance()->get(Coverage::class);
        } catch (Throwable) {
            return false;
        }

        return $coverage->coverage === true;
    }
}
