<?php

declare(strict_types=1);

namespace Pest\Plugins;

use NunoMaduro\Collision\Adapters\Phpunit\Printers\DefaultPrinter;
use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Contracts\Plugins\Terminable;
use Pest\Plugins\Tia\BaselineSync;
use Pest\Plugins\Tia\ChangedFiles;
use Pest\Plugins\Tia\Contracts\State;
use Pest\Plugins\Tia\CoverageCollector;
use Pest\Plugins\Tia\Fingerprint;
use Pest\Plugins\Tia\Graph;
use Pest\Plugins\Tia\Recorder;
use Pest\Plugins\Tia\ResultCollector;
use Pest\Plugins\Tia\WatchPatterns;
use Pest\Support\Container;
use Pest\TestSuite;
use PHPUnit\Framework\TestStatus\TestStatus;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Test Impact Analysis (file-level, parallel-aware).
 *
 * Modes
 * -----
 *  - **Record** — no graph (or fingerprint / recording commit drifted). The
 *    full suite runs with PCOV / Xdebug capture per test; the resulting
 *    `test → [source_file, …]` edges land in `.temp/tia/graph.json`.
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
 *    into `.temp/tia/worker-edges-<TEST_TOKEN>.json`.
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
     * State keys under which TIA persists its blobs. Kept here as constants
     * (rather than scattered strings) so the storage layout is visible in
     * one place, and so `CoverageMerger` can reference the same keys. All
     * files live under `.temp/tia/` — the `tia-` filename prefix is gone
     * because the directory already namespaces them.
     */
    public const string KEY_GRAPH = 'graph.json';

    public const string KEY_AFFECTED = 'affected.json';

    private const string KEY_WORKER_EDGES_PREFIX = 'worker-edges-';

    private const string KEY_WORKER_RESULTS_PREFIX = 'worker-results-';

    /**
     * Raw-serialised `CodeCoverage` snapshot from the last `--tia --coverage`
     * run. Stored as bytes so the backend stays JSON/file-agnostic — the
     * merger un/serialises rather than `require`-ing a PHP file.
     */
    public const string KEY_COVERAGE_CACHE = 'coverage.bin';

    /**
     * Marker key dropped by `Tia` to tell `Support\Coverage` to apply the
     * merge. Absent on plain `--coverage` runs so non-TIA usage keeps its
     * current (narrow) behaviour.
     */
    public const string KEY_COVERAGE_MARKER = 'coverage.marker';

    /**
     * Global flag toggled by the parent process so workers know to record.
     */
    private const string RECORDING_GLOBAL = 'TIA_RECORDING';

    /**
     * Global flag that tells workers to install the TIA filter (replay mode).
     * Workers read the affected set from `.temp/tia/affected.json`.
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
     * Counter-part of `$replayedCount`: every time `getCachedResult()`
     * decides the test must execute (affected, unknown, or no cached
     * result), we bump this. Together the two counters let the summary
     * show "affected + replayed" in units of test methods, not test
     * files, matching the "Tests: N" total Pest prints above.
     */
    private int $executedCount = 0;

    /**
     * Cached assertion count per test id for the current replay run. Keyed
     * by `ClassName::methodName`; populated when `getCachedResult()` hits
     * cache and drained by `Testable::__runTest()` on the short-circuit
     * path so the emitted count matches the recorded run.
     *
     * @var array<string, int>
     */
    private array $cachedAssertionsByTestId = [];

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

    private function workerEdgesKey(string $token): string
    {
        return self::KEY_WORKER_EDGES_PREFIX.$token.'.json';
    }

    private function workerResultsKey(string $token): string
    {
        return self::KEY_WORKER_RESULTS_PREFIX.$token.'.json';
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
        private readonly State $state,
        private readonly BaselineSync $baselineSync,
    ) {}

    /**
     * Convenience wrapper: load + decode the graph, or return `null` if no
     * graph has been stored. Any call that needs to mutate + re-save the
     * graph also goes through `saveGraph()` to keep bytes flowing through
     * the `State` abstraction rather than filesystem paths.
     */
    private function loadGraph(string $projectRoot): ?Graph
    {
        $json = $this->state->read(self::KEY_GRAPH);

        if ($json === null) {
            return null;
        }

        return Graph::decode($json, $projectRoot);
    }

    private function saveGraph(Graph $graph): bool
    {
        $json = $graph->encode();

        if ($json === null) {
            return false;
        }

        return $this->state->write(self::KEY_GRAPH, $json);
    }

    /**
     * Returns the cached result for the given test, or `null` if the test
     * must run (affected, unknown, or no replay mode active).
     */
    public function getCachedResult(string $filename, string $testId): ?TestStatus
    {
        if (! $this->replayGraph instanceof Graph) {
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
            $this->executedCount++;

            return null;
        }

        // Unknown files (not in graph) must execute — they're new.
        if ($rel === null || ! $this->replayGraph->knowsTest($rel)) {
            $this->executedCount++;

            return null;
        }

        // Known + unaffected: return cached result if we have one for this
        // branch (falls back to main if branch is fresh).
        $result = $this->replayGraph->getResult($this->branch, $testId);

        if ($result instanceof TestStatus) {
            $this->replayedCount++;
            // Cache the assertion count alongside the status so `Testable`
            // can emit the exact `addToAssertionCount()` at replay time
            // without hitting the graph twice per test.
            $assertions = $this->replayGraph->getAssertions($this->branch, $testId);
            $this->cachedAssertionsByTestId[$testId] = $assertions ?? 0;
        } else {
            // Graph knows the test file but has no stored result for this
            // specific test id (new test, or first time seeing this method).
            // It must execute.
            $this->executedCount++;
        }

        return $result;
    }

    /**
     * Exact assertion count captured for the given test during its last
     * recorded run. Returns `0` if unknown (new test, or old graph entry
     * pre-dating assertion-count tracking). `Testable::__runTest` reads
     * this to feed `addToAssertionCount()` instead of defaulting to 1.
     */
    public function getCachedAssertions(string $testId): int
    {
        return $this->cachedAssertionsByTestId[$testId] ?? 0;
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

        // Flush the ResultCollector + replay counter from workers into a
        // partial so the parent can merge them. Needed during replay so the
        // summary is accurate, and also during the initial record run so
        // the graph lands with results on first write — otherwise the next
        // run would load a graph with edges but empty results, miss the
        // cache for every test, and look pointlessly slow.
        if (Parallel::isWorker() && ($this->replayGraph instanceof Graph || $this->recordingActive)) {
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
            $this->flushWorkerPartial($perTest);
            $recorder->reset();
            $this->coverageCollector->reset();

            return;
        }

        // Non-parallel record path: straight into the main cache.
        $changedFiles = new ChangedFiles($projectRoot);
        $currentSha = $changedFiles->currentSha();

        $graph = $this->loadGraph($projectRoot) ?? new Graph($projectRoot);
        $graph->setFingerprint(Fingerprint::compute($projectRoot));
        $graph->setRecordedAtSha($this->branch, $currentSha);
        // Snapshot whatever is currently dirty in the working tree. Without
        // this, the very first `--tia` replay would see those same files
        // via `since()` and report them as "changed" — even though they're
        // identical to what we just recorded against.
        $graph->setLastRunTree(
            $this->branch,
            $changedFiles->snapshotTree($changedFiles->since($currentSha) ?? []),
        );
        $graph->replaceEdges($perTest);
        $graph->pruneMissingTests();

        if (! $this->saveGraph($graph)) {
            $this->output->writeln('  <fg=red>TIA</> failed to write graph.');
            $recorder->reset();

            return;
        }

        $this->output->writeln(sprintf(
            '  <fg=green>TIA</> graph recorded (%d test files).',
            count($perTest),
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
        // In parallel runs the workers executed the tests, so their
        // ResultCollector + replay counter live in other processes. Pull
        // those partials in first — both replay and record paths need them:
        // replay to make the summary accurate, record so the initial graph
        // lands with results instead of a second "warm-up" run being needed
        // before replay is actually fast.
        if (Parallel::isEnabled()) {
            $this->mergeWorkerReplayPartials();
        }

        if ($this->replayRan) {
            $this->bumpRecordedSha();
        }

        if ((string) Parallel::getGlobal(self::RECORDING_GLOBAL) !== '1') {
            // Series path: graph was already written by `terminate()` (or
            // nothing to record). Snapshot results now so they ride along.
            $this->snapshotTestResults();

            return $exitCode;
        }

        $projectRoot = TestSuite::getInstance()->rootPath;
        $partialKeys = $this->collectWorkerEdgesPartials();

        if ($partialKeys === []) {
            return $exitCode;
        }

        $changedFiles = new ChangedFiles($projectRoot);
        $currentSha = $changedFiles->currentSha();

        $graph = $this->loadGraph($projectRoot) ?? new Graph($projectRoot);
        $graph->setFingerprint(Fingerprint::compute($projectRoot));
        $graph->setRecordedAtSha($this->branch, $currentSha);
        // Snapshot any currently-dirty files so the first replay run
        // doesn't mis-report them as changed. See the series record path.
        $graph->setLastRunTree(
            $this->branch,
            $changedFiles->snapshotTree($changedFiles->since($currentSha) ?? []),
        );

        $merged = [];

        foreach ($partialKeys as $key) {
            $data = $this->readPartial($key);

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

            $this->state->delete($key);
        }

        $finalised = [];

        foreach ($merged as $testFile => $sourceSet) {
            $finalised[$testFile] = array_keys($sourceSet);
        }

        // Empty-edges guard: if every worker returned no edges it almost
        // always means the coverage driver wasn't loaded in the workers
        // (common footgun with custom PHP ini scan dirs, Herd profiles,
        // stripped CI runners). Writing the empty graph would silently
        // seed a broken baseline; fail loud instead.
        if ($finalised === []) {
            $this->output->writeln([
                '',
                '  <fg=white;bg=red> ERROR </> TIA recorded zero edges — coverage driver likely missing.',
                '  Install / enable <fg=cyan>pcov</> or <fg=cyan>xdebug</> (mode: coverage) in the worker PHP and retry.',
                '',
            ]);

            return $exitCode;
        }

        $graph->replaceEdges($finalised);
        $graph->pruneMissingTests();

        if (! $this->saveGraph($graph)) {
            $this->output->writeln('  <fg=red>TIA</> failed to write graph.');

            return $exitCode;
        }

        $this->output->writeln(sprintf(
            '  <fg=green>TIA</> graph recorded (%d test files, %d worker partials).',
            count($finalised),
            count($partialKeys),
        ));

        // Persist per-test results (merged from worker partials above) into
        // the freshly-written graph. Without this the graph would ship with
        // edges but no results, and the very next `--tia` run would miss
        // cache for every test even though nothing changed.
        $this->snapshotTestResults();

        return $exitCode;
    }

    /**
     * Compares a loaded graph's fingerprint to the current one and decides
     * how much of the graph is still usable.
     *
     *   - **Structural drift** (composer.lock, Pest.php, factory codegen,
     *     schema bump): edges themselves are potentially wrong → discard
     *     the whole graph + coverage cache and return null. Caller falls
     *     through to record mode.
     *   - **Environmental drift** (PHP minor, extension set, Pest version):
     *     edges describe the code correctly; only the cached per-test
     *     results were captured against a different runtime and might not
     *     reproduce. Drop `baselines[branch].results` + coverage cache,
     *     bump the fingerprint to the current env, persist. Caller uses
     *     the graph for edges; results refill naturally during this run's
     *     replay (every test misses cache, runs normally, seeds results).
     *   - **Match**: return the graph untouched.
     *
     * @param  array{structural: array<string, mixed>, environmental: array<string, mixed>}  $current
     */
    private function reconcileFingerprint(Graph $graph, array $current): ?Graph
    {
        $stored = $graph->fingerprint();

        if (! Fingerprint::structuralMatches($stored, $current)) {
            $this->output->writeln(
                '  <fg=yellow>TIA</> graph structure outdated — rebuilding.',
            );
            $this->state->delete(self::KEY_GRAPH);
            $this->state->delete(self::KEY_COVERAGE_CACHE);

            return null;
        }

        $drift = Fingerprint::environmentalDrift($stored, $current);

        if ($drift !== []) {
            $this->output->writeln(sprintf(
                '  <fg=yellow>TIA</> env differs from baseline (%s) — results dropped, edges reused.',
                implode(', ', $drift),
            ));

            $graph->clearResults($this->branch);
            $graph->setFingerprint($current);
            $this->saveGraph($graph);
            $this->state->delete(self::KEY_COVERAGE_CACHE);
        }

        return $graph;
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

        $fingerprint = Fingerprint::compute($projectRoot);

        $graph = $forceRebuild ? null : $this->loadGraph($projectRoot);

        if ($graph instanceof Graph) {
            $graph = $this->reconcileFingerprint($graph, $fingerprint);
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

        // No local graph and not being forced to rebuild from scratch: try
        // to pull a team-shared baseline so fresh checkouts (new devs, CI
        // containers) don't pay the full record cost. If the pull succeeds
        // the graph is re-read and reconciled against the local env.
        if (! $graph instanceof Graph && ! $forceRebuild && $this->baselineSync->fetchIfAvailable($projectRoot)) {
            $graph = $this->loadGraph($projectRoot);
            if ($graph instanceof Graph) {
                $graph = $this->reconcileFingerprint($graph, $fingerprint);
            }
        }

        // Drop the marker so `Support\Coverage::report()` knows to merge the
        // current (narrow) coverage with the cached full-run snapshot. Plain
        // `--coverage` runs don't drop it, so their behaviour is untouched.
        if ($this->piggybackCoverage) {
            $this->state->write(self::KEY_COVERAGE_MARKER, '');
        }

        // First `--tia --coverage` run has nothing to merge against: if we
        // replay, the coverage driver sees only the affected tests and the
        // report collapses to near-zero coverage. Fall back to recording
        // (full suite) to seed the cache for next time.
        if ($this->piggybackCoverage && ! $this->state->exists(self::KEY_COVERAGE_CACHE)) {
            return $this->enterRecordMode($arguments);
        }

        if ($graph instanceof Graph) {
            return $this->enterReplayMode($graph, $projectRoot, $arguments);
        }

        return $this->enterRecordMode($arguments);
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
        $graph = $this->loadGraph($projectRoot);

        if (! $graph instanceof Graph) {
            return;
        }

        $raw = $this->state->read(self::KEY_AFFECTED);

        if ($raw === null) {
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

        $affectedSet = array_fill_keys($affected, true);

        $this->replayRan = true;
        $this->replayGraph = $graph;
        $this->affectedFiles = $affectedSet;

        $this->registerRecap();

        if (! Parallel::isEnabled()) {
            return $arguments;
        }

        // Parallel: persist affected set so workers can install the filter.
        if (! $this->persistAffectedSet($affected)) {
            $this->output->writeln(
                '  <fg=red>TIA</> failed to persist affected set — running full suite.',
            );

            return $arguments;
        }

        // Clear stale partials from a previous interrupted run so the merge
        // pass doesn't pick up results from an unrelated invocation.
        $this->purgeWorkerPartials();

        Parallel::setGlobal(self::REPLAYING_GLOBAL, '1');

        return $arguments;
    }

    /**
     * @param  array<int, string>  $affected  Project-relative paths.
     */
    private function persistAffectedSet(array $affected): bool
    {
        $json = json_encode(array_values($affected), JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return false;
        }

        return $this->state->write(self::KEY_AFFECTED, $json);
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function enterRecordMode(array $arguments): array
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
            $this->purgeWorkerPartials();

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
    private function flushWorkerPartial(array $perTest): void
    {
        $json = json_encode($perTest, JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return;
        }

        $this->state->write($this->workerEdgesKey($this->workerToken()), $json);
    }

    /**
     * @return list<string> State keys of per-worker edges partials.
     */
    private function collectWorkerEdgesPartials(): array
    {
        return $this->state->keysWithPrefix(self::KEY_WORKER_EDGES_PREFIX);
    }

    private function purgeWorkerPartials(): void
    {
        foreach ($this->collectWorkerEdgesPartials() as $key) {
            $this->state->delete($key);
        }
        foreach ($this->collectWorkerReplayPartials() as $key) {
            $this->state->delete($key);
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

        if ($results === [] && $this->replayedCount === 0 && $this->executedCount === 0) {
            return;
        }

        $json = json_encode([
            'results' => $results,
            'replayed' => $this->replayedCount,
            'executed' => $this->executedCount,
        ], JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return;
        }

        $this->state->write($this->workerResultsKey($this->workerToken()), $json);
    }

    /**
     * @return list<string> State keys of per-worker replay partials.
     */
    private function collectWorkerReplayPartials(): array
    {
        return $this->state->keysWithPrefix(self::KEY_WORKER_RESULTS_PREFIX);
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

        foreach ($this->collectWorkerReplayPartials() as $key) {
            $raw = $this->state->read($key);
            $this->state->delete($key);

            if ($raw === null) {
                continue;
            }

            $decoded = json_decode($raw, true);

            if (! is_array($decoded)) {
                continue;
            }

            if (isset($decoded['replayed']) && is_int($decoded['replayed'])) {
                $this->replayedCount += $decoded['replayed'];
            }

            if (isset($decoded['executed']) && is_int($decoded['executed'])) {
                $this->executedCount += $decoded['executed'];
            }

            if (isset($decoded['results']) && is_array($decoded['results'])) {
                $normalised = [];

                /** @var mixed $result */
                foreach ($decoded['results'] as $testId => $result) {
                    if (! is_string($testId)) {
                        continue;
                    }
                    if (! is_array($result)) {
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
    private function readPartial(string $key): ?array
    {
        $raw = $this->state->read($key);

        if ($raw === null) {
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
     * Hooks a recap callback into Collision's `DefaultPrinter` so TIA's
     * counts ride along the "Tests: N passed (M assertions, ...)" line
     * instead of printing on their own block. Collision joins each
     * callback's return value with a gray `, ` separator, so we return
     * a single fragment like `728 replayed via tia` (or nothing when
     * there's no replay activity to report).
     */
    private function registerRecap(): void
    {
        DefaultPrinter::addRecap(function (): string {
            $fragments = [];

            if ($this->executedCount > 0) {
                $fragments[] = $this->executedCount.' affected';
            }

            if ($this->replayedCount > 0) {
                $fragments[] = $this->replayedCount.' replayed';
            }

            return implode(', ', $fragments);
        });
    }

    private function bumpRecordedSha(): void
    {
        $projectRoot = TestSuite::getInstance()->rootPath;

        $graph = $this->loadGraph($projectRoot);

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

        $this->saveGraph($graph);
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

        $projectRoot = TestSuite::getInstance()->rootPath;

        $graph = $this->loadGraph($projectRoot);

        if (! $graph instanceof Graph) {
            return;
        }

        foreach ($results as $testId => $result) {
            $graph->setResult(
                $this->branch,
                $testId,
                $result['status'],
                $result['message'],
                $result['time'],
                $result['assertions'],
            );
        }

        $this->saveGraph($graph);
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
