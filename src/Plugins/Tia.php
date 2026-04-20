<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Contracts\Plugins\Terminable;
use PHPUnit\Framework\TestStatus\TestStatus;
use Pest\Plugins\Tia\ChangedFiles;
use Pest\Plugins\Tia\CoverageCollector;
use Pest\Plugins\Tia\Fingerprint;
use Pest\Plugins\Tia\Graph;
use Pest\Plugins\Tia\Recorder;
use Pest\Plugins\Tia\ResultCollector;
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
final class Tia implements AddsOutput, HandlesArguments, Terminable
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

    /**
     * Cache file holding PHPUnit's `CodeCoverage` object from the last
     * `--tia --coverage` run. When the next run replays most tests from
     * the TIA graph, only the affected tests produce fresh coverage; the
     * rest is merged in from this cache so the report stays complete.
     */
    private const string COVERAGE_CACHE_FILE = 'tia-coverage.php';

    /**
     * Marker file dropped by `Tia` to tell `Support\Coverage` to apply the
     * merge. Absent on plain `--coverage` runs so non-TIA usage keeps its
     * current (narrow) behaviour.
     */
    private const string COVERAGE_MARKER_FILE = 'tia-coverage.marker';

    private const string WORKER_PREFIX = 'tia-worker-';

    private const string WORKER_RESULTS_PREFIX = 'tia-worker-results-';

    /**
     * Global flag toggled by the parent process so workers know to record.
     */
    private const string RECORDING_GLOBAL = 'TIA_RECORDING';

    /**
     * Global flag that tells workers to install the TIA filter (replay mode).
     * Workers read the affected set from `.temp/tia-affected.json`.
     */
    private const string REPLAYING_GLOBAL = 'TIA_REPLAYING';

    /**
     * Global flag that tells workers to piggyback on PHPUnit's coverage
     * driver (set by the parent whenever `--tia --coverage` is used). Workers
     * can't infer this from their own argv because paratest forwards only
     * `--coverage-php=<path>` — not the `--coverage` flag Pest's Coverage
     * plugin inspects.
     */
    private const string PIGGYBACK_COVERAGE_GLOBAL = 'TIA_PIGGYBACK_COVERAGE';

    private bool $graphWritten = false;

    private bool $replayRan = false;

    /**
     * Counts cache hits during a replay run. Incremented each time
     * `getCachedResult()` returns a non-null status so the end-of-run
     * summary reflects what actually happened, not a graph-level estimate.
     */
    private int $replayedCount = 0;

    /**
     * Captured at replay setup so the end-of-run summary can report the
     * scope of the changes that drove the run.
     */
    private int $changedFileCount = 0;

    /**
     * Captured at replay setup — number of tests the graph flagged as
     * affected (i.e. should re-execute). May overshoot the actually-
     * executed count when the user narrows with a path filter.
     */
    private int $affectedTestCount = 0;

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

    private static function workerResultsPath(string $token): string
    {
        return self::tempDir().DIRECTORY_SEPARATOR.self::WORKER_RESULTS_PREFIX.$token.'.json';
    }

    private static function workerResultsGlob(): string
    {
        return self::tempDir().DIRECTORY_SEPARATOR.self::WORKER_RESULTS_PREFIX.'*.json';
    }

    public static function coverageCachePath(): string
    {
        return self::tempDir().DIRECTORY_SEPARATOR.self::COVERAGE_CACHE_FILE;
    }

    public static function coverageMarkerPath(): string
    {
        return self::tempDir().DIRECTORY_SEPARATOR.self::COVERAGE_MARKER_FILE;
    }

    /**
     * True when TIA is piggybacking on PHPUnit's own coverage driver. Toggled
     * in `handleArguments` whenever `--tia` runs alongside `--coverage` so
     * both the parent and workers read edges from the shared `CodeCoverage`
     * instance instead of starting a second PCOV / Xdebug session.
     */
    private bool $piggybackCoverage = false;

    /**
     * True once we have committed to recording in this process — either by
     * activating our own `Recorder` or by delegating to PHPUnit's coverage
     * driver via `CoverageCollector`. `terminate()` only flushes when this
     * is set, so runs that never entered record mode don't poke the graph.
     */
    private bool $recordingActive = false;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly Recorder $recorder,
        private readonly CoverageCollector $coverageCollector,
        private readonly WatchPatterns $watchPatterns,
    ) {}

    /**
     * Returns the cached result for the given test, or `null` if the test
     * must run (affected, unknown, or no replay mode active).
     */
    public function getCachedResult(string $filename, string $testId): ?TestStatus
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
        $result = $this->replayGraph->getResult($this->branch, $testId);

        if ($result !== null) {
            $this->replayedCount++;
        }

        return $result;
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

        // When `--coverage` is active, piggyback on PHPUnit's CodeCoverage
        // instead of starting our own PCOV / Xdebug session. Running two
        // collectors against the same driver corrupts both — so we let
        // PHPUnit drive, and read per-test edges from the shared instance
        // at the end of the run via `CoverageCollector`. Workers can't
        // detect `--coverage` from their own argv (paratest strips it,
        // keeping only `--coverage-php=<path>`) so the parent broadcasts
        // via a global.
        $this->piggybackCoverage = $isWorker
            ? (string) Parallel::getGlobal(self::PIGGYBACK_COVERAGE_GLOBAL) === '1'
            : $this->coverageReportActive();

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

        // Worker in replay mode: flush the ResultCollector + replay counter
        // into a partial so the parent can merge them into the graph after
        // paratest returns. Parent's own ResultCollector is empty in parallel
        // runs because workers — not the parent — execute the tests.
        if (Parallel::isWorker() && $this->replayGraph !== null) {
            $this->flushWorkerReplay();
        }

        $recorder = $this->recorder;

        if (! $this->recordingActive && ! $recorder->isActive()) {
            return;
        }

        $this->graphWritten = true;

        $projectRoot = TestSuite::getInstance()->rootPath;
        $perTest = $this->piggybackCoverage
            ? $this->coverageCollector->perTestFiles()
            : $recorder->perTestFiles();

        if ($perTest === []) {
            $recorder->reset();
            $this->coverageCollector->reset();

            return;
        }

        if (Parallel::isWorker()) {
            $this->flushWorkerPartial($projectRoot, $perTest);
            $recorder->reset();
            $this->coverageCollector->reset();

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
        $this->coverageCollector->reset();
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
            // In parallel runs the workers executed the tests, so their
            // ResultCollector + replay counter live in other processes. Pull
            // those partials in before both the summary and the graph
            // snapshot so the parent state reflects the whole run.
            if (Parallel::isEnabled()) {
                $this->mergeWorkerReplayPartials();
            }

            $this->bumpRecordedSha();
            $this->emitReplaySummary();
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

        // Drop the marker so `Support\Coverage::report()` knows to merge the
        // current (narrow) coverage with the cached full-run snapshot. Plain
        // `--coverage` runs don't drop it, so their behaviour is untouched.
        if ($this->piggybackCoverage) {
            @file_put_contents(self::coverageMarkerPath(), '');
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
            $this->installWorkerReplay($projectRoot);

            return $arguments;
        }

        if (! $recordingGlobal) {
            return $arguments;
        }

        // Piggyback: PHPUnit starts its coverage driver, `CoverageCollector`
        // harvests the per-test edges in `terminate()`. The Recorder stays
        // idle — starting our own driver would corrupt PHPUnit's data.
        if ($this->piggybackCoverage) {
            $this->recordingActive = true;

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
        $this->recordingActive = true;

        return $arguments;
    }

    /**
     * Wires worker-side replay. Mirrors the series path: sets `replayGraph`
     * + `affectedFiles` so the `BeforeEachable` hook in `beforeEach()` can
     * answer per-test. Unaffected tests replay their cached status (pass,
     * fail, skip, todo, incomplete) so the user sees the full suite report
     * in parallel runs exactly like in series.
     */
    private function installWorkerReplay(string $projectRoot): void
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

        $this->replayGraph = $graph;
        $this->affectedFiles = $affectedSet;
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

        $this->changedFileCount = count($changed);
        $this->affectedTestCount = count($affected);

        $affectedSet = array_fill_keys($affected, true);

        $this->replayRan = true;
        $this->replayGraph = $graph;
        $this->affectedFiles = $affectedSet;

        if (! Parallel::isEnabled()) {
            return $arguments;
        }

        // Parallel: persist affected set so workers can install the filter.
        if (! $this->persistAffectedSet($projectRoot, $affected)) {
            $this->output->writeln(
                '  <fg=red>TIA</> failed to persist affected set — running full suite.',
            );

            return $arguments;
        }

        // Clear stale partials from a previous interrupted run so the merge
        // pass doesn't pick up results from an unrelated invocation.
        $this->purgeWorkerPartials($projectRoot);

        Parallel::setGlobal(self::REPLAYING_GLOBAL, '1');

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
        $recorder = $this->recorder;

        // Piggyback: PHPUnit's coverage driver is already running under
        // `--coverage`. We don't need our own driver — `CoverageCollector`
        // harvests the per-test edges from PHPUnit's shared `CodeCoverage`
        // at terminate time. Skip the driver check entirely in this mode.
        if (! $this->piggybackCoverage && ! $recorder->driverAvailable()) {
            // Both series and parallel record require the coverage driver.
            // Parallel also requires it because workers inherit the parent's
            // PHP config — if the parent lacks the driver, workers will too
            // and would silently produce no graph. Warn once, up-front, and
            // continue running the suite without TIA so the user still gets
            // their test results.
            $this->emitCoverageDriverMissing();

            return $arguments;
        }

        if (Parallel::isEnabled()) {
            // Parent driving `--parallel`: workers will do the actual
            // recording. We only advertise the intent through a global.
            // Clean up any stale partial files from a previous interrupted
            // run so the merge step doesn't confuse itself.
            $this->purgeWorkerPartials($projectRoot);

            Parallel::setGlobal(self::RECORDING_GLOBAL, '1');

            if ($this->piggybackCoverage) {
                Parallel::setGlobal(self::PIGGYBACK_COVERAGE_GLOBAL, '1');
            }

            $this->output->writeln($this->piggybackCoverage
                ? '  <fg=cyan>TIA</> recording dependency graph in parallel via `--coverage` (first run) — '.
                    'subsequent `--tia` runs will only re-execute affected tests.'
                : '  <fg=cyan>TIA</> recording dependency graph in parallel (first run) — '.
                    'subsequent `--tia` runs will only re-execute affected tests.');

            return $arguments;
        }

        if ($this->piggybackCoverage) {
            $this->recordingActive = true;

            $this->output->writeln(
                '  <fg=cyan>TIA</> recording dependency graph via `--coverage` (first run) — '.
                'subsequent `--tia` runs will only re-execute affected tests.',
            );

            return $arguments;
        }

        $recorder->activate();
        $this->recordingActive = true;

        $this->output->writeln(sprintf(
            '  <fg=cyan>TIA</> recording dependency graph via %s (first run) — '.
            'subsequent `--tia` runs will only re-execute affected tests.',
            $recorder->driver(),
        ));

        return $arguments;
    }

    private function emitCoverageDriverMissing(): void
    {
        $this->output->writeln([
            '',
            '  <fg=black;bg=yellow> WARNING </> No coverage driver is available — TIA skipped.',
            '',
            '  TIA needs <fg=cyan>ext-pcov</> or <fg=cyan>Xdebug</> with <fg=cyan>coverage</> mode enabled to record',
            '  the dependency graph. Install or enable one and rerun with `--tia`.',
            '',
        ]);
    }

    /**
     * @param  array<string, array<int, string>>  $perTest
     */
    private function flushWorkerPartial(string $projectRoot, array $perTest): void
    {
        $path = self::workerPath($this->workerToken());
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

        foreach ($this->collectWorkerReplayPartials() as $path) {
            @unlink($path);
        }
    }

    /**
     * Worker-side flush of replay state (collected results + cache-hit
     * counter) into a per-worker partial file. Parent merges them in
     * `addOutput` so the graph snapshot + summary reflect the full run.
     */
    private function flushWorkerReplay(): void
    {
        /** @var ResultCollector $collector */
        $collector = Container::getInstance()->get(ResultCollector::class);

        $results = $collector->all();

        if ($results === [] && $this->replayedCount === 0) {
            return;
        }

        $token = $this->workerToken();
        $path = self::workerResultsPath($token);
        $dir = dirname($path);

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return;
        }

        $json = json_encode([
            'results' => $results,
            'replayed' => $this->replayedCount,
        ], JSON_UNESCAPED_SLASHES);

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
    private function collectWorkerReplayPartials(): array
    {
        $matches = glob(self::workerResultsGlob());

        return $matches === false ? [] : $matches;
    }

    /**
     * Parent-side merge of per-worker replay partials. Feeds the results into
     * the parent's `ResultCollector` so the existing snapshot pass persists
     * them, and rolls up the cache-hit counts so the summary is accurate.
     */
    private function mergeWorkerReplayPartials(): void
    {
        /** @var ResultCollector $collector */
        $collector = Container::getInstance()->get(ResultCollector::class);

        foreach ($this->collectWorkerReplayPartials() as $path) {
            $raw = @file_get_contents($path);

            if ($raw === false) {
                @unlink($path);

                continue;
            }

            $decoded = json_decode($raw, true);
            @unlink($path);

            if (! is_array($decoded)) {
                continue;
            }

            if (isset($decoded['replayed']) && is_int($decoded['replayed'])) {
                $this->replayedCount += $decoded['replayed'];
            }

            if (isset($decoded['results']) && is_array($decoded['results'])) {
                $normalised = [];

                /** @var mixed $result */
                foreach ($decoded['results'] as $testId => $result) {
                    if (! is_string($testId) || ! is_array($result)) {
                        continue;
                    }

                    $normalised[$testId] = [
                        'status' => is_int($result['status'] ?? null) ? $result['status'] : 0,
                        'message' => is_string($result['message'] ?? null) ? $result['message'] : '',
                        'time' => is_float($result['time'] ?? null) || is_int($result['time'] ?? null) ? (float) $result['time'] : 0.0,
                        'assertions' => is_int($result['assertions'] ?? null) ? $result['assertions'] : 0,
                    ];
                }

                if ($normalised !== []) {
                    $collector->merge($normalised);
                }
            }
        }
    }

    private function workerToken(): string
    {
        $raw = $_SERVER['TEST_TOKEN'] ?? $_ENV['TEST_TOKEN'] ?? null;

        $token = is_scalar($raw) ? (string) $raw : (string) getmypid();
        $token = preg_replace('/[^A-Za-z0-9_-]/', '', $token);

        if ($token === null || $token === '') {
            return (string) getmypid();
        }

        return $token;
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
    /**
     * Prints the post-run TIA summary. Runs after the test report so the
     * replayed count reflects what actually happened (cache hits counted
     * inside `getCachedResult`) rather than a graph-level estimate that
     * ignores any CLI path filter the user passed in.
     */
    private function emitReplaySummary(): void
    {
        $this->output->writeln(sprintf(
            '  <fg=green>TIA</> %d changed file(s) → %d affected, %d replayed.',
            $this->changedFileCount,
            $this->affectedTestCount,
            $this->replayedCount,
        ));
    }

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
