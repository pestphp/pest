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
use Pest\Plugins\Tia\JsModuleGraph;
use Pest\Plugins\Tia\Recorder;
use Pest\Plugins\Tia\ResultCollector;
use Pest\Plugins\Tia\Storage;
use Pest\Plugins\Tia\TableExtractor;
use Pest\Plugins\Tia\WatchPatterns;
use Pest\Exceptions\NoAffectedTestsFound;
use Pest\Panic;
use Pest\Support\Container;
use Pest\TestCaseFilters\TiaTestCaseFilter;
use Pest\TestSuite;
use PHPUnit\Framework\TestStatus\TestStatus;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Test Impact Analysis plugin — record/replay, parallel-aware.
 *
 * Must be registered before `Parallel` — Parallel exits on `--parallel`,
 * so later plugins never execute.
 *
 * @internal
 */
final class Tia implements AddsOutput, HandlesArguments, Terminable
{
    use Concerns\HandleArguments;

    private const string OPTION = '--tia';

    private const string FRESH_OPTION = '--fresh';

    private const string REFETCH_OPTION = '--refetch';

    private const string FILTERED_OPTION = '--filtered';

    public const string KEY_GRAPH = 'graph.json';

    public const string KEY_AFFECTED = 'affected.json';

    private const string KEY_WORKER_EDGES_PREFIX = 'worker-edges-';

    private const string KEY_WORKER_RESULTS_PREFIX = 'worker-results-';

    /** Sentinel dropped by a recording worker without a usable coverage driver. */
    private const string KEY_WORKER_NO_DRIVER_PREFIX = 'worker-no-driver-';

    public const string KEY_COVERAGE_CACHE = 'coverage.bin';

    public const string KEY_COVERAGE_MARKER = 'coverage.marker';

    public const string KEY_FETCH_COOLDOWN = 'fetch-cooldown.json';

    private const string RECORDING_GLOBAL = 'TIA_RECORDING';

    private const string REPLAYING_GLOBAL = 'TIA_REPLAYING';

    /** Tells workers to apply TiaTestCaseFilter instead of cache short-circuiting. */
    private const string FILTERED_GLOBAL = 'TIA_FILTERED';

    /** Workers can't detect `--coverage` from their own argv — paratest strips it. */
    private const string PIGGYBACK_COVERAGE_GLOBAL = 'TIA_PIGGYBACK_COVERAGE';

    private bool $graphWritten = false;

    private bool $replayRan = false;

    private int $replayedCount = 0;

    private int $affectedCount = 0;

    private int $executedCount = 0;

    /** @var array<string, int> */
    private array $cachedAssertionsByTestId = [];

    private ?Graph $replayGraph = null;

    private string $branch = 'main';

    /** @var array<string, true> */
    private array $affectedFiles = [];

    private function workerEdgesKey(string $token): string
    {
        return self::KEY_WORKER_EDGES_PREFIX.$token.'.json';
    }

    private function workerResultsKey(string $token): string
    {
        return self::KEY_WORKER_RESULTS_PREFIX.$token.'.json';
    }

    private bool $piggybackCoverage = false;

    private bool $recordingActive = false;

    private bool $forceRefetch = false;

    /** Prevents fetching the same stale baseline twice after structural drift. */
    private bool $baselineFetchAttemptedForDrift = false;

    /** Gates `Graph::pruneMissingTests()` — only safe on full `--fresh` rebuilds. */
    private bool $freshRebuild = false;

    private bool $filteredMode = false;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly Recorder $recorder,
        private readonly CoverageCollector $coverageCollector,
        private readonly WatchPatterns $watchPatterns,
        private readonly State $state,
        private readonly BaselineSync $baselineSync,
    ) {}

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

    public function getCachedResult(string $filename, string $testId): ?TestStatus
    {
        if (! $this->replayGraph instanceof Graph) {
            return null;
        }

        $projectRoot = TestSuite::getInstance()->rootPath;
        $real = @realpath($filename);
        $rel = $real !== false
            ? str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen(rtrim($projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)))
            : null;

        if ($rel !== null && isset($this->affectedFiles[$rel])) {
            $this->affectedCount++;
            $this->executedCount++;

            return null;
        }

        if ($rel === null || ! $this->replayGraph->knowsTest($rel)) {
            $this->executedCount++;

            return null;
        }

        $result = $this->replayGraph->getResult($this->branch, $testId);

        if ($result instanceof TestStatus) {
            if ($result->isFailure() || $result->isError()) {
                $this->executedCount++;

                return null;
            }

            $this->replayedCount++;
            $assertions = $this->replayGraph->getAssertions($this->branch, $testId);
            $this->cachedAssertionsByTestId[$testId] = $assertions ?? 0;
        } else {
            $this->executedCount++;
        }

        return $result;
    }

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

        /** @var Tia\WatchPatterns $watchPatterns */
        $watchPatterns = Container::getInstance()->get(Tia\WatchPatterns::class);
        $cliEnabled = $this->hasArgument(self::OPTION, $arguments);
        $alwaysEnabled = $watchPatterns->isEnabled()
            && (! $watchPatterns->isLocally() || Environment::name() === Environment::LOCAL);
        $enabled = $cliEnabled || $alwaysEnabled;
        $this->filteredMode = $this->hasArgument(self::FILTERED_OPTION, $arguments) || $watchPatterns->isFiltered();
        $freshRequested = $this->hasArgument(self::FRESH_OPTION, $arguments);
        $this->forceRefetch = $this->hasArgument(self::REFETCH_OPTION, $arguments);

        // Always strip TIA-owned flags so they never reach PHPUnit, even when
        // TIA is not active for this run.
        $arguments = $this->popArgument(self::OPTION, $arguments);
        $arguments = $this->popArgument(self::FRESH_OPTION, $arguments);
        $arguments = $this->popArgument(self::REFETCH_OPTION, $arguments);
        $arguments = $this->popArgument(self::FILTERED_OPTION, $arguments);

        $forceRebuild = $freshRequested && ($enabled || $recordingGlobal || $replayingGlobal);
        $this->freshRebuild = $forceRebuild;

        if (! $enabled && ! $this->forceRefetch && ! $recordingGlobal && ! $replayingGlobal) {
            return $arguments;
        }

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

        $perTestTables = $recorder->perTestTables();
        $perTestInertia = $recorder->perTestInertiaComponents();
        $perTestUsesDatabase = $recorder->perTestUsesDatabase();

        if ($perTestUsesDatabase !== []) {
            $perTestTables = $this->augmentDatabaseTestTables(
                $perTestTables,
                $perTestUsesDatabase,
                $projectRoot,
            );
        }

        if (Parallel::isWorker()) {
            $this->flushWorkerPartial($perTest, $perTestTables, $perTestInertia);
            $recorder->reset();
            $this->coverageCollector->reset();

            return;
        }

        $changedFiles = new ChangedFiles($projectRoot);
        $currentSha = $changedFiles->currentSha();

        $graph = $this->loadGraph($projectRoot) ?? new Graph($projectRoot);
        $graph->setFingerprint(Fingerprint::compute($projectRoot));
        $graph->setRecordedAtSha($this->branch, $currentSha);
        $graph->setLastRunTree(
            $this->branch,
            $changedFiles->snapshotTree($changedFiles->since($currentSha) ?? []),
        );
        $graph->replaceEdges($perTest);
        $graph->replaceTestTables($perTestTables);
        $graph->replaceTestInertiaComponents($perTestInertia);
        $graph->replaceJsFileToComponents(JsModuleGraph::build($projectRoot));

        if ($this->freshRebuild) {
            $graph->pruneMissingTests();
        }

        $this->seedResultsInto($graph);

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

    public function addOutput(int $exitCode): int
    {
        if (Parallel::isWorker()) {
            return $exitCode;
        }

        $this->reportMissingWorkerDrivers();

        if (Parallel::isEnabled()) {
            $this->mergeWorkerReplayPartials();
        }

        if ($this->replayRan) {
            $this->bumpRecordedSha();
        }

        if ((string) Parallel::getGlobal(self::RECORDING_GLOBAL) !== '1') {
            $this->snapshotTestResults();

            return $exitCode;
        }

        $projectRoot = TestSuite::getInstance()->rootPath;
        $partialKeys = $this->collectWorkerEdgesPartials();

        if ($partialKeys === []) {
            if ($this->replayRan) {
                $this->snapshotTestResults();
            }

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

        $mergedFiles = [];
        $mergedTables = [];
        $mergedInertia = [];

        foreach ($partialKeys as $key) {
            $data = $this->readPartial($key);

            if ($data === null) {
                continue;
            }

            foreach ($data['files'] as $testFile => $sources) {
                if (! isset($mergedFiles[$testFile])) {
                    $mergedFiles[$testFile] = [];
                }

                foreach ($sources as $source) {
                    $mergedFiles[$testFile][$source] = true;
                }
            }

            foreach ($data['tables'] as $testFile => $tables) {
                if (! isset($mergedTables[$testFile])) {
                    $mergedTables[$testFile] = [];
                }

                foreach ($tables as $table) {
                    $mergedTables[$testFile][$table] = true;
                }
            }

            foreach ($data['inertia'] as $testFile => $components) {
                if (! isset($mergedInertia[$testFile])) {
                    $mergedInertia[$testFile] = [];
                }

                foreach ($components as $component) {
                    $mergedInertia[$testFile][$component] = true;
                }
            }

            $this->state->delete($key);
        }

        $finalised = [];

        foreach ($mergedFiles as $testFile => $sourceSet) {
            $finalised[$testFile] = array_keys($sourceSet);
        }

        $finalisedTables = [];

        foreach ($mergedTables as $testFile => $tableSet) {
            $finalisedTables[$testFile] = array_keys($tableSet);
        }

        $finalisedInertia = [];

        foreach ($mergedInertia as $testFile => $componentSet) {
            $finalisedInertia[$testFile] = array_keys($componentSet);
        }

        if ($finalised === []) {
            if ($this->replayRan) {
                $this->snapshotTestResults();

                return $exitCode;
            }

            $this->output->writeln([
                '',
                '  <fg=white;bg=red> ERROR </> TIA recorded zero edges — coverage driver likely missing.',
                '  Install / enable <fg=cyan>pcov</> or <fg=cyan>xdebug</> (mode: coverage) in the worker PHP and retry.',
                '',
            ]);

            return $exitCode;
        }

        $graph->replaceEdges($finalised);
        $graph->replaceTestTables($finalisedTables);
        $graph->replaceTestInertiaComponents($finalisedInertia);
        $graph->replaceJsFileToComponents(JsModuleGraph::build($projectRoot));

        if ($this->freshRebuild) {
            $graph->pruneMissingTests();
        }

        if (! $this->saveGraph($graph)) {
            $this->output->writeln('  <fg=red>TIA</> failed to write graph.');

            return $exitCode;
        }

        $this->output->writeln(sprintf(
            '  <fg=green>TIA</> graph recorded (%d test files, %d worker partials).',
            count($finalised),
            count($partialKeys),
        ));

        $this->snapshotTestResults();

        return $exitCode;
    }

    /**
     * Structural drift → discard graph, return null (caller enters record mode).
     * Environmental drift → drop results, keep edges, return updated graph.
     * Match → return graph unchanged.
     *
     * @param  array{structural: array<string, mixed>, environmental: array<string, mixed>}  $current
     */
    private function reconcileFingerprint(Graph $graph, array $current): ?Graph
    {
        $stored = $graph->fingerprint();

        if (! Fingerprint::structuralMatches($stored, $current)) {
            $drift = Fingerprint::structuralDrift($stored, $current);

            $this->output->writeln(sprintf(
                '  <fg=yellow>TIA</> graph structure outdated (%s).',
                $this->formatStructuralDrift($drift),
            ));

            if (in_array('composer_lock', $drift, true)) {
                $branchSha = $graph->recordedAtSha($this->branch);
                if ($branchSha !== null) {
                    $summary = $this->composerLockDelta(
                        TestSuite::getInstance()->rootPath,
                        $branchSha,
                    );
                    if ($summary !== '') {
                        $this->output->writeln('    <fg=gray>'.$summary.'</>');
                    }
                }
            }

            $rebuilt = $this->tryRemoteBaselineForDrift($current);

            if ($rebuilt instanceof Graph) {
                return $this->reconcileFingerprint($rebuilt, $current);
            }

            $this->output->writeln('  <fg=yellow>TIA</> rebuilding graph from scratch.');

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
        $this->watchPatterns->useDefaults($projectRoot);
        $this->branch = (new ChangedFiles($projectRoot))->currentBranch() ?? 'main';

        $fingerprint = Fingerprint::compute($projectRoot);

        // `--fresh` is meant to be a clean slate: nuke the entire per-project
        // state dir up front (graph, baseline, worker partials, fingerprint,
        // JS module cache, coverage marker, etc.). Wiping per-key in code
        // would leave room for stale entries we forgot about — most
        // recently, status-7/8 result entries with no `file` that survived
        // a rebuild and kept tripping `hasUnlocatedFailuresOrErrors()` on
        // subsequent `--filtered` runs. Safe here because `handleParent`
        // runs in the parent before any worker is spawned.
        if ($forceRebuild) {
            Storage::purge($projectRoot);
        }

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
        if (! $graph instanceof Graph
            && ! $forceRebuild
            && ! $this->baselineFetchAttemptedForDrift
            && $this->baselineSync->fetchIfAvailable($projectRoot, $this->forceRefetch)) {
            $this->baselineFetchAttemptedForDrift = true;
            $graph = $this->loadGraph($projectRoot);
            if ($graph instanceof Graph) {
                $graph = $this->reconcileFingerprint($graph, $fingerprint);
            }
        }

        if ($this->piggybackCoverage) {
            $this->state->write(self::KEY_COVERAGE_MARKER, '');
        }

        // Kick off the JS module graph resolver in the background so it
        // runs in parallel with the test suite. By the time the flush
        // path calls `JsModuleGraph::build()`, the result is usually
        // already on stdout and `wait()` returns instantly. Cheap when
        // the cache is fresh — the warmer fingerprint-checks first and
        // skips spawning Node entirely.
        if (! Parallel::isWorker() && JsModuleGraph::isApplicable($projectRoot)) {
            JsModuleGraph::warmInBackground($projectRoot);
        }

        // First `--tia --coverage` run: no cache to merge against yet, must record the full suite.
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
            $this->installWorkerReplay($projectRoot);

            if ($recordingGlobal) {
                return $this->activateWorkerRecorderForReplay($arguments);
            }

            return $arguments;
        }

        if (! $recordingGlobal) {
            return $arguments;
        }

        if ($this->piggybackCoverage) {
            $this->recordingActive = true;

            return $arguments;
        }

        $recorder = $this->recorder;

        if (! $recorder->driverAvailable()) {
            $this->state->write(
                self::KEY_WORKER_NO_DRIVER_PREFIX.$this->workerToken().'.json',
                '{}',
            );

            return $arguments;
        }

        $recorder->activate();
        $this->recordingActive = true;

        return $arguments;
    }

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

        if ((string) Parallel::getGlobal(self::FILTERED_GLOBAL) === '1') {
            TestSuite::getInstance()->tests->addTestCaseFilter(
                new TiaTestCaseFilter($projectRoot, $graph, $affectedSet),
            );
        }
    }

    /**
     * During replay, affected tests execute normally. If a coverage driver is
     * available, record those executions too so refactors that introduce new
     * dependencies update the graph without requiring a full `--fresh` run.
     * Cached tests short-circuit before `Recorder::beginTest()`, so they don't
     * produce empty replacement edges.
     *
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function activateWorkerRecorderForReplay(array $arguments): array
    {
        if ($this->piggybackCoverage) {
            $this->recordingActive = true;

            return $arguments;
        }

        $recorder = $this->recorder;

        if (! $recorder->driverAvailable()) {
            $this->state->write(
                self::KEY_WORKER_NO_DRIVER_PREFIX.$this->workerToken().'.json',
                '{}',
            );

            return $arguments;
        }

        $recorder->activate();
        $this->recordingActive = true;

        return $arguments;
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

        $branchSha = $graph->recordedAtSha($this->branch);
        $changed = $changedFiles->since($branchSha) ?? [];

        $changed = $changedFiles->filterUnchangedSinceLastRun(
            $changed,
            $graph->lastRunTree($this->branch),
            $branchSha,
        );

        $hasProjectPhpSourceChanges = $this->hasProjectPhpSourceChanges($changed);
        $coverageAvailable = $this->piggybackCoverage || $this->recorder->driverAvailable();

        if ($hasProjectPhpSourceChanges && ! $coverageAvailable) {
            $this->output->writeln([
                '',
                '  <fg=black;bg=yellow> WARNING </> TIA detected PHP source changes but no coverage driver is available.',
                '  Running the full suite to avoid using a stale dependency graph. Install / enable <fg=cyan>pcov</> or <fg=cyan>xdebug</> (mode: coverage) so TIA can safely refresh edges after PHP refactors.',
                '',
            ]);

            return $arguments;
        }

        $affected = $changed === [] ? [] : $graph->affected($changed);

        if ($this->filteredMode) {
            if ($graph->hasUnlocatedFailuresOrErrors($this->branch)) {
                $this->output->writeln([
                    '',
                    '  <fg=yellow>TIA</> cached failures/errors exist but this baseline cannot map them to files — running full suite. Re-run with <fg=cyan>--fresh</> to rebuild the baseline.',
                    '',
                ]);

                return $arguments;
            }

            $affected = array_values(array_unique([
                ...$affected,
                ...$graph->failedOrErroredTestFiles($this->branch),
            ]));
        }

        $affectedSet = array_fill_keys($affected, true);
        $canRefreshReplayEdges = $affected !== [] && $coverageAvailable;

        $this->replayRan = true;
        $this->replayGraph = $graph;
        $this->affectedFiles = $affectedSet;

        $this->registerRecap();

        if ($this->filteredMode) {
            if ($affected === []) {
                Panic::with(new NoAffectedTestsFound);
            }

            TestSuite::getInstance()->tests->addTestCaseFilter(
                new TiaTestCaseFilter($projectRoot, $graph, $affectedSet),
            );
        }

        if (! Parallel::isEnabled()) {
            if ($canRefreshReplayEdges) {
                $this->recorder->activate();
                $this->recordingActive = true;
            }

            return $arguments;
        }

        if (! $this->persistAffectedSet($affected)) {
            $this->output->writeln(
                '  <fg=red>TIA</> failed to persist affected set — running full suite.',
            );

            return $arguments;
        }

        $this->purgeWorkerPartials();

        Parallel::setGlobal(self::REPLAYING_GLOBAL, '1');

        if ($canRefreshReplayEdges) {
            Parallel::setGlobal(self::RECORDING_GLOBAL, '1');
        }

        if ($this->filteredMode) {
            Parallel::setGlobal(self::FILTERED_GLOBAL, '1');
        }

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

        if (! $this->piggybackCoverage && ! $recorder->driverAvailable()) {
            $this->emitCoverageDriverMissing();

            return $arguments;
        }

        if (Parallel::isEnabled()) {
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
     * @param  array<string, array<int, string>>  $perTestFiles
     * @param  array<string, array<int, string>>  $perTestTables
     * @param  array<string, array<int, string>>  $perTestInertiaComponents
     */
    private function flushWorkerPartial(array $perTestFiles, array $perTestTables, array $perTestInertiaComponents): void
    {
        $json = json_encode([
            'files' => $perTestFiles,
            'tables' => $perTestTables,
            'inertia' => $perTestInertiaComponents,
        ], JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return;
        }

        $this->state->write($this->workerEdgesKey($this->workerToken()), $json);
    }

    private function collectWorkerEdgesPartials(): array
    {
        return $this->state->keysWithPrefix(self::KEY_WORKER_EDGES_PREFIX);
    }

    private function reportMissingWorkerDrivers(): void
    {
        $keys = $this->state->keysWithPrefix(self::KEY_WORKER_NO_DRIVER_PREFIX);

        if ($keys === []) {
            return;
        }

        foreach ($keys as $key) {
            $this->state->delete($key);
        }

        $this->output->writeln(sprintf(
            '  <fg=yellow>TIA</> %d worker(s) had no coverage driver — their per-test edges and results were dropped. '
                .'Install / enable <fg=cyan>pcov</> or <fg=cyan>xdebug</> (mode: coverage) in the worker PHP and rerun.',
            count($keys),
        ));
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

    private function flushWorkerReplay(): void
    {
        /** @var ResultCollector $collector */
        $collector = Container::getInstance()->get(ResultCollector::class);

        $results = $collector->all();

        if ($results === [] && $this->replayedCount === 0 && $this->affectedCount === 0 && $this->executedCount === 0) {
            return;
        }

        $json = json_encode([
            'results' => $results,
            'replayed' => $this->replayedCount,
            'affected' => $this->affectedCount,
            'executed' => $this->executedCount,
        ], JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return;
        }

        $this->state->write($this->workerResultsKey($this->workerToken()), $json);
    }

    private function collectWorkerReplayPartials(): array
    {
        return $this->state->keysWithPrefix(self::KEY_WORKER_RESULTS_PREFIX);
    }

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

            if (isset($decoded['affected']) && is_int($decoded['affected'])) {
                $this->affectedCount += $decoded['affected'];
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

                    if (isset($result['file']) && is_string($result['file'])) {
                        $normalised[$testId]['file'] = $result['file'];
                    }
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
     * @return array{files: array<string, array<int, string>>, tables: array<string, array<int, string>>, inertia: array<string, array<int, string>>}|null
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

        $filesSource = is_array($data['files'] ?? null) ? $data['files'] : [];
        $tablesSource = is_array($data['tables'] ?? null) ? $data['tables'] : [];
        $inertiaSource = is_array($data['inertia'] ?? null) ? $data['inertia'] : [];

        return [
            'files' => $this->cleanPartialSection($filesSource),
            'tables' => $this->cleanPartialSection($tablesSource),
            'inertia' => $this->cleanPartialSection($inertiaSource),
        ];
    }

    /**
     * @param  array<mixed, mixed>  $section
     * @return array<string, array<int, string>>
     */
    private function cleanPartialSection(array $section): array
    {
        $out = [];

        foreach ($section as $test => $items) {
            if (! is_string($test)) {
                continue;
            }
            if (! is_array($items)) {
                continue;
            }

            $clean = [];

            foreach ($items as $item) {
                if (is_string($item)) {
                    $clean[] = $item;
                }
            }

            $out[$test] = $clean;
        }

        return $out;
    }

    private function registerRecap(): void
    {
        DefaultPrinter::addRecap(function (): string {
            // mergeWorkerReplayPartials fires before addOutput on --parallel, which is intentional:
            // partial keys are deleted on read so the later addOutput call becomes a no-op.
            if (Parallel::isEnabled() && ! Parallel::isWorker()) {
                $this->mergeWorkerReplayPartials();
            }

            $fragments = [];

            if ($this->affectedCount > 0) {
                $fragments[] = $this->affectedCount.' affected';
            }

            $uncachedCount = max(0, $this->executedCount - $this->affectedCount);

            if ($uncachedCount > 0) {
                $fragments[] = $uncachedCount.' uncached';
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

        $workingTreeFiles = $changedFiles->since($currentSha) ?? [];
        $graph->setLastRunTree($this->branch, $changedFiles->snapshotTree($workingTreeFiles));

        $this->saveGraph($graph);
    }

    private function seedResultsInto(Graph $graph): void
    {
        /** @var ResultCollector $collector */
        $collector = Container::getInstance()->get(ResultCollector::class);

        foreach ($collector->all() as $testId => $result) {
            $graph->setResult(
                $this->branch,
                $testId,
                $result['status'],
                $result['message'],
                $result['time'],
                $result['assertions'],
                $result['file'] ?? null,
            );
        }

        $collector->reset();
    }

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
                $result['file'] ?? null,
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

    /**
     * PHP source changes can introduce new dependencies. Without a coverage
     * driver, replay can run the currently affected tests but cannot refresh
     * the graph, so a later edit to the newly introduced dependency could be
     * missed. Treat those runs as full-suite unless coverage can self-heal.
     *
     * @param  array<int, string>  $changedFiles
     */
    private function hasProjectPhpSourceChanges(array $changedFiles): bool
    {
        foreach ($changedFiles as $rel) {
            if (! is_string($rel)) {
                continue;
            }

            if (! str_ends_with($rel, '.php')) {
                continue;
            }

            if (str_ends_with($rel, '.blade.php')) {
                continue;
            }

            if (str_starts_with($rel, 'tests/')
                || str_starts_with($rel, 'vendor/')
                || str_starts_with($rel, 'storage/framework/')
                || str_starts_with($rel, 'bootstrap/cache/')) {
                continue;
            }

            if (! is_file(TestSuite::getInstance()->rootPath.DIRECTORY_SEPARATOR.$rel)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param  array{structural: array<string, mixed>, environmental: array<string, mixed>}  $current
     */
    private function tryRemoteBaselineForDrift(array $current): ?Graph
    {
        if ($this->baselineFetchAttemptedForDrift) {
            return null;
        }

        $projectRoot = TestSuite::getInstance()->rootPath;
        $this->baselineFetchAttemptedForDrift = true;

        if (! $this->baselineSync->fetchIfAvailable($projectRoot, $this->forceRefetch)) {
            return null;
        }

        $fetched = $this->loadGraph($projectRoot);

        if (! $fetched instanceof Graph) {
            return null;
        }

        if (! Fingerprint::structuralMatches($fetched->fingerprint(), $current)) {
            $this->output->writeln(
                '  <fg=yellow>TIA</> fetched baseline still drifts — discarding.',
            );

            return null;
        }

        $this->output->writeln(
            '  <fg=green>TIA</> fetched baseline matches — skipping local rebuild.',
        );

        return $fetched;
    }

    /**
     * @param  list<string>  $drift
     */
    private function formatStructuralDrift(array $drift): string
    {
        static $labels = [
            'composer_lock' => 'composer.lock',
            'composer_json' => 'composer.json',
            'phpunit_xml' => 'phpunit.xml',
            'phpunit_xml_dist' => 'phpunit.xml.dist',
            'vite_config' => 'vite.config',
            'package_json' => 'package.json',
            'package_lock' => 'Node lockfile',
            'js_config' => 'JS/TS config',
            'pest_factory' => 'Pest internals',
            'pest_method_factory' => 'Pest internals',
        ];

        $seen = [];
        foreach ($drift as $key) {
            $seen[$labels[$key] ?? $key] = true;
        }

        if ($seen === []) {
            return 'unknown';
        }

        return implode(', ', array_keys($seen));
    }

    private function composerLockDelta(string $projectRoot, string $sha): string
    {
        $current = @file_get_contents($projectRoot.'/composer.lock');
        if ($current === false) {
            return '';
        }

        $process = new Process(['git', 'show', $sha.':composer.lock'], $projectRoot);
        $process->setTimeout(5.0);
        $process->run();

        if (! $process->isSuccessful()) {
            return '';
        }

        $oldVersions = $this->lockVersions($process->getOutput());
        $newVersions = $this->lockVersions($current);

        if ($oldVersions === [] && $newVersions === []) {
            return '';
        }

        $changes = [];
        foreach ($newVersions as $name => $version) {
            if (! isset($oldVersions[$name])) {
                $changes[] = '+ '.$name.' '.$version;
            } elseif ($oldVersions[$name] !== $version) {
                $changes[] = $name.' '.$oldVersions[$name].' → '.$version;
            }
        }
        foreach ($oldVersions as $name => $version) {
            if (! isset($newVersions[$name])) {
                $changes[] = '− '.$name.' '.$version;
            }
        }

        if ($changes === []) {
            return '';
        }

        sort($changes);

        $maxShown = 8;
        if (count($changes) > $maxShown) {
            $extra = count($changes) - $maxShown;
            $changes = array_slice($changes, 0, $maxShown);
            $changes[] = sprintf('… +%d more', $extra);
        }

        return implode(', ', $changes);
    }

    /**
     * @param  array<string, array<int, string>>  $perTestTables
     * @param  array<string, true>  $perTestUsesDatabase
     * @return array<string, array<int, string>>
     */
    private function augmentDatabaseTestTables(array $perTestTables, array $perTestUsesDatabase, string $projectRoot): array
    {
        $migrationDir = rtrim($projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';

        if (! is_dir($migrationDir)) {
            return $perTestTables;
        }

        $allTables = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($migrationDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile()) {
                continue;
            }
            if (! str_ends_with(strtolower((string) $fileInfo->getPathname()), '.php')) {
                continue;
            }

            $content = @file_get_contents((string) $fileInfo->getPathname());

            if ($content === false) {
                continue;
            }

            foreach (TableExtractor::fromMigrationSource($content) as $table) {
                $allTables[strtolower($table)] = true;
            }
        }

        if ($allTables === []) {
            return $perTestTables;
        }

        foreach (array_keys($perTestUsesDatabase) as $testFile) {
            $existing = $perTestTables[$testFile] ?? [];
            $merged = array_fill_keys($existing, true) + $allTables;
            $names = array_keys($merged);
            sort($names);
            $perTestTables[$testFile] = $names;
        }

        return $perTestTables;
    }

    /**
     * @return array<string, string> package name → version
     */
    private function lockVersions(string $json): array
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            return [];
        }

        $out = [];

        foreach (['packages', 'packages-dev'] as $section) {
            if (! isset($data[$section])) {
                continue;
            }
            if (! is_array($data[$section])) {
                continue;
            }
            foreach ($data[$section] as $package) {
                if (! is_array($package)) {
                    continue;
                }
                $name = $package['name'] ?? null;
                $version = $package['version'] ?? null;

                if (is_string($name) && is_string($version)) {
                    $out[$name] = $version;
                }
            }
        }

        return $out;
    }
}
