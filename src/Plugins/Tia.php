<?php

declare(strict_types=1);

namespace Pest\Plugins;

use NunoMaduro\Collision\Adapters\Phpunit\Printers\DefaultPrinter;
use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Contracts\Plugins\HandlesOriginalArguments;
use Pest\Contracts\Plugins\Terminable;
use Pest\Exceptions\InvalidOption;
use Pest\Exceptions\MissingDependency;
use Pest\Exceptions\NoAffectedTestsFound;
use Pest\Exceptions\TiaRequiresCommit;
use Pest\Exceptions\TiaRequiresDefaultBranch;
use Pest\Exceptions\TiaRequiresRemote;
use Pest\Exceptions\TiaRequiresRepositoryRoot;
use Pest\Panic;
use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Tia\BaselineSync;
use Pest\Plugins\Tia\ChangedFiles;
use Pest\Plugins\Tia\CiDefaultBranch;
use Pest\Plugins\Tia\Contracts\State;
use Pest\Plugins\Tia\CoverageCollector;
use Pest\Plugins\Tia\Fingerprint;
use Pest\Plugins\Tia\Graph;
use Pest\Plugins\Tia\JsModuleGraph;
use Pest\Plugins\Tia\Recorder;
use Pest\Plugins\Tia\ResultCollector;
use Pest\Plugins\Tia\SourceScope;
use Pest\Plugins\Tia\Storage;
use Pest\Plugins\Tia\TableExtractor;
use Pest\Plugins\Tia\WatchPatterns;
use Pest\Support\Container;
use Pest\Support\Git;
use Pest\Support\View;
use Pest\TestCaseFilters\TiaTestCaseFilter;
use Pest\TestSuite;
use PHPUnit\Framework\TestStatus\TestStatus;
use PHPUnit\TestRunner\TestResult\Facade as TestResultFacade;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Tia implements AddsOutput, HandlesArguments, HandlesOriginalArguments, Terminable
{
    use HandleArguments;

    private const string OPTION = '--tia';

    private const string NO_OPTION = '--no-tia';

    private const string FRESH_OPTION = '--fresh';

    private const string REFETCH_OPTION = '--refetch';

    private const string FILTERED_OPTION = '--filtered';

    private const string LOCALLY_OPTION = '--locally';

    private const string BASELINED_OPTION = '--baselined';

    private const string BASELINE_PATH_OPTION = '--baseline';

    private const string MUTATE_OPTION = '--mutate';

    private const string ENV_MUTATION_TESTING = 'PEST_MUTATION_TESTING';

    private const string ENV_TIA = 'PEST_TIA';

    private const string ENV_FILTERED = 'PEST_TIA_FILTERED';

    private const string ENV_LOCALLY = 'PEST_TIA_LOCALLY';

    private const string ENV_BASELINED = 'PEST_TIA_BASELINED';

    public const string KEY_GRAPH = 'graph.json';

    public const string KEY_AFFECTED = 'affected.json';

    private const string KEY_WORKER_EDGES_PREFIX = 'worker-edges-';

    private const string KEY_WORKER_RESULTS_PREFIX = 'worker-results-';

    private const string KEY_WORKER_NO_DRIVER_PREFIX = 'worker-no-driver-';

    public const string KEY_COVERAGE_CACHE = 'coverage.bin.gz';

    public const string KEY_COVERAGE_MARKER = 'coverage.marker';

    public const string KEY_FETCH_COOLDOWN = 'fetch-cooldown.json';

    private const string RECORDING_GLOBAL = 'TIA_RECORDING';

    private const string REPLAYING_GLOBAL = 'TIA_REPLAYING';

    private const string FILTERED_GLOBAL = 'TIA_FILTERED';

    private const string WORKER_RESULTS_GLOBAL = 'TIA_WORKER_RESULTS';

    private const string PIGGYBACK_COVERAGE_GLOBAL = 'TIA_PIGGYBACK_COVERAGE';

    private const string FALLBACK_BRANCH_GLOBAL = 'TIA_FALLBACK_BRANCH';

    private const string DEFAULT_BRANCH = 'main';

    /**
     * @var list<string>
     */
    private const array VALUE_TAKING_FLAGS = [
        '-c', '--configuration', '--bootstrap', '--cache-directory',
        '--filter', '--group', '--exclude-group', '--covers', '--uses',
        '--test-suffix', '--testsuite', '--exclude-testsuite',
        '--printer', '--columns', '--colors', '--order-by', '--random-order-seed',
        '--include-path', '--whitelist',
        '--log-junit', '--log-teamcity', '--testdox-html', '--testdox-text',
        '--coverage-clover', '--coverage-cobertura', '--coverage-crap4j',
        '--coverage-html', '--coverage-openclover', '--coverage-php',
        '--coverage-text', '--coverage-xml',
        '--coverage-filter', '--path-coverage',
        '--repeat', '--retry-times', '--memory-limit', '--seed',
        '--compact', '--ci-build-id', '--min',
    ];

    /** @var list<string> */
    private const array COVERAGE_REPORT_FLAGS = [
        '--coverage-clover', '--coverage-cobertura', '--coverage-crap4j',
        '--coverage-html', '--coverage-openclover', '--coverage-php',
        '--coverage-text', '--coverage-xml',
    ];

    /** @var list<string> */
    private const array PARTIAL_SELECTION_FLAGS = [
        '--filter', '--exclude-filter', '--group', '--exclude-group',
        '--covers', '--uses', '--testsuite', '--exclude-testsuite', '--test-suffix',
        '--dirty', '--todo', '--todos', '--flaky', '--notes',
        '--assignee', '--issue', '--ticket', '--pr', '--pull-request',
    ];

    private const array UNSUPPORTED_OPTIONS = [
        '--covers', '--uses', '--random-order-seed',
    ];

    private bool $graphWritten = false;

    private bool $replayRan = false;

    private int $replayedCount = 0;

    private int $affectedCount = 0;

    private int $executedCount = 0;

    /** @var array<string, int> */
    private array $cachedAssertionsByTestId = [];

    /** @var array<string, array{status: int, message: string}> */
    private array $cachedStatusByTestId = [];

    /** @var array<string, float> */
    private array $cachedTimeByTestId = [];

    private ?Graph $replayGraph = null;

    private string $branch = self::DEFAULT_BRANCH;

    private string $fallbackBranch = self::DEFAULT_BRANCH;

    private bool $fallbackBranchResolved = false;

    private bool $branchResolved = false;

    /** @var array<string, true> */
    private array $affectedFiles = [];

    /** @var array{structural: array<string, mixed>, environmental: array<string, mixed>}|null */
    private ?array $startFingerprint = null;

    private bool $piggybackCoverage = false;

    private bool $recordingActive = false;

    private bool $forceRefetch = false;

    private bool $baselineFetchAttemptedForDrift = false;

    private bool $filteredMode = false;

    private bool $writesSuppressed = false;

    private bool $resultsOnlyWrites = false;

    private bool $flushesWorkerResults = false;

    private bool $unreadableGraphReported = false;

    private bool $detachedHead = false;

    private bool $graphUnreachable = false;

    private bool $fullSuiteFallbackRan = false;

    /** @var array<int, string> */
    private array $originalArguments = [];

    private ?string $driftLabel = null;

    private ?string $driftDetails = null;

    private ?string $freshGraphReason = null;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly Recorder $recorder,
        private readonly CoverageCollector $coverageCollector,
        private readonly WatchPatterns $watchPatterns,
        private readonly State $state,
        private readonly BaselineSync $baselineSync,
    ) {}

    private function renderBadge(string $type, string $content): void
    {
        View::render('components.badge', ['type' => $type, 'content' => $content]);
    }

    private function renderChild(string $text): void
    {
        $this->output->writeln(sprintf('  <fg=gray>─ %s</>', $text));
    }

    /**
     * @param  array{structural: array<string, mixed>, environmental: array<string, mixed>}  $current
     */
    private function structuralFingerprintShifted(array $current): bool
    {
        assert($this->startFingerprint !== null);

        return ! Fingerprint::structuralMatches($this->startFingerprint, $current);
    }

    private function loadGraph(string $projectRoot): ?Graph
    {
        $json = $this->state->read(self::KEY_GRAPH);

        if ($json === null) {
            return null;
        }

        $graph = Graph::decode($json, $projectRoot);

        if (! $graph instanceof Graph) {
            $this->discardUnreadableGraph();

            return null;
        }

        $graph->setFallbackBranch($this->fallbackBranch);

        return $graph;
    }

    private function discardUnreadableGraph(): void
    {
        if (Parallel::isWorker()) {
            return;
        }

        if (! $this->deleteState(self::KEY_GRAPH)) {
            return;
        }

        if ($this->unreadableGraphReported) {
            return;
        }

        $this->unreadableGraphReported = true;

        $this->output->writeln('');
        $this->renderBadge('WARN', 'The dependency graph could not be read — it will be rebuilt.');
    }

    private function deleteState(string $key): bool
    {
        if ($this->detachedHead) {
            return false;
        }

        return $this->state->delete($key);
    }

    private function saveGraph(Graph $graph): bool
    {
        if ($this->detachedHead) {
            return true;
        }

        $json = $graph->encode();

        if ($json === null) {
            return false;
        }

        return $this->state->write(self::KEY_GRAPH, $json);
    }

    /**
     * @param  array<int, string>  $arguments
     */
    public static function isEnabledForRun(array $arguments): bool
    {
        if (self::argumentPresent(self::NO_OPTION, $arguments)) {
            return false;
        }

        $watchPatterns = Container::getInstance()->get(WatchPatterns::class);
        assert($watchPatterns instanceof WatchPatterns);

        self::applyWatchPatternMarks($arguments, $watchPatterns);

        if (self::argumentPresent(self::OPTION, $arguments) || self::envFlagEnabled(self::ENV_TIA)) {
            return true;
        }

        if (! $watchPatterns->isEnabled()) {
            return false;
        }
        if (! $watchPatterns->isLocally()) {
            return true;
        }

        return ! self::argumentPresent('--ci', $arguments);
    }

    /**
     * @param  array<int, string>  $arguments
     */
    public static function isMutationRun(array $arguments): bool
    {
        if (self::argumentPresent(self::MUTATE_OPTION, $arguments)) {
            return true;
        }

        return getenv(self::ENV_MUTATION_TESTING) !== false;
    }

    public static function recordsEdgesInWorkers(): bool
    {
        return (string) Parallel::getGlobal(self::RECORDING_GLOBAL) === '1'
            && (string) Parallel::getGlobal(self::PIGGYBACK_COVERAGE_GLOBAL) !== '1';
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private static function applyWatchPatternMarks(array $arguments, WatchPatterns $watchPatterns): void
    {
        if (self::argumentPresent(self::LOCALLY_OPTION, $arguments) || self::envFlagEnabled(self::ENV_LOCALLY)) {
            $watchPatterns->markEnabled();
            $watchPatterns->markLocally();
        }

        if (self::argumentPresent(self::BASELINED_OPTION, $arguments) || self::envFlagEnabled(self::ENV_BASELINED)) {
            $watchPatterns->markBaselined();
        }
    }

    /**
     * Mirrors {@see HandleArguments::hasArgument()} for
     *
     * @param  array<int, string>  $arguments
     */
    private static function argumentPresent(string $argument, array $arguments): bool
    {
        foreach ($arguments as $arg) {
            if ($arg === $argument) {
                return true;
            }

            if (str_starts_with((string) $arg, "$argument=")) { // @phpstan-ignore-line
                return true;
            }
        }

        return false;
    }

    private static function envFlagEnabled(string $name): bool
    {
        return filter_var(getenv($name), FILTER_VALIDATE_BOOL);
    }

    public function getStatus(string $filename, string $testId): ?TestStatus
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
            if ($this->replayGraph->shouldRerunStatus($result)) {
                $this->executedCount++;

                return null;
            }

            $this->replayedCount++;
            $this->cachedStatusByTestId[$testId] = [
                'status' => $result->asInt(),
                'message' => $result->message(),
            ];
            $assertions = $this->replayGraph->getAssertions($this->branch, $testId);
            $this->cachedAssertionsByTestId[$testId] = $assertions ?? 0;

            $time = $this->replayGraph->getTime($this->branch, $testId);

            if ($time !== null) {
                $this->cachedTimeByTestId[$testId] = $time;
            }
        } else {
            $this->executedCount++;
        }

        return $result;
    }

    public function getAssertionCount(string $testId): int
    {
        return $this->cachedAssertionsByTestId[$testId] ?? 0;
    }

    /**
     * {@inheritDoc}
     */
    public function handleOriginalArguments(array $arguments): void
    {
        $this->originalArguments = $arguments;
    }

    /**
     * {@inheritDoc}
     */
    public function handleArguments(array $arguments): array
    {
        if ($this->hasArgument(self::BASELINE_PATH_OPTION, $arguments)) {
            $this->output->writeln(Storage::tempDir(TestSuite::getInstance()->rootPath));

            exit(0);
        }

        $isWorker = Parallel::isWorker();
        $recordingGlobal = $isWorker && (string) Parallel::getGlobal(self::RECORDING_GLOBAL) === '1';
        $replayingGlobal = $isWorker && (string) Parallel::getGlobal(self::REPLAYING_GLOBAL) === '1';

        /** @var WatchPatterns $watchPatterns */
        $watchPatterns = Container::getInstance()->get(WatchPatterns::class);
        self::applyWatchPatternMarks($arguments, $watchPatterns);
        $disabled = $this->hasArgument(self::NO_OPTION, $arguments);
        $cliEnabled = $this->hasArgument(self::OPTION, $arguments) || self::envFlagEnabled(self::ENV_TIA);
        $alwaysEnabled = $watchPatterns->isEnabled()
            && (! $watchPatterns->isLocally() || Environment::name() === Environment::LOCAL);
        if (! $isWorker && ! $disabled && ($cliEnabled || $alwaysEnabled)) {
            $this->guardUnsupportedOptions($arguments);
        }

        $hasExplicitPath = $this->hasExplicitPathArgument($arguments);
        $partial = ! $isWorker && ($hasExplicitPath || $this->hasPartialSelection($arguments));
        $disabled = $disabled || $partial;

        $mutating = self::isMutationRun($this->originalArguments);

        if (getenv(self::ENV_MUTATION_TESTING) !== false) {
            $this->writesSuppressed = true;
        }
        $enabled = ! $disabled && ($cliEnabled || $alwaysEnabled);
        $this->filteredMode = ($this->hasArgument(self::FILTERED_OPTION, $arguments) || self::envFlagEnabled(self::ENV_FILTERED) || $watchPatterns->isFiltered())
            && ! $hasExplicitPath
            && ! $this->coverageReportActive();
        $freshRequested = $this->hasArgument(self::FRESH_OPTION, $arguments);
        $this->forceRefetch = $this->hasArgument(self::REFETCH_OPTION, $arguments);

        $arguments = $this->popArgument(self::OPTION, $arguments);
        $arguments = $this->popArgument(self::NO_OPTION, $arguments);
        $arguments = $this->popArgument(self::FRESH_OPTION, $arguments);
        $arguments = $this->popArgument(self::REFETCH_OPTION, $arguments);
        $arguments = $this->popArgument(self::FILTERED_OPTION, $arguments);
        $arguments = $this->popArgument(self::LOCALLY_OPTION, $arguments);
        $arguments = $this->popArgument(self::BASELINED_OPTION, $arguments);

        if ($disabled) {
            $this->requestWorkerResults();

            if ($partial) {
                $this->resultsOnlyWrites = true;

                if ($cliEnabled || $freshRequested || $this->forceRefetch || $this->filteredMode) {
                    $this->output->writeln('');
                    $this->renderChild('TIA does not apply to partial runs — running the selected tests directly.');
                }
            }

            $this->forceRefetch = false;
            $this->filteredMode = false;

            return $arguments;
        }

        if (! $isWorker && $mutating && ($enabled || $this->forceRefetch)) {
            $this->requestWorkerResults();
            $this->emitMutationScopedRecordSkipped();

            $this->forceRefetch = false;
            $this->filteredMode = false;

            return $arguments;
        }

        if ($isWorker && (string) Parallel::getGlobal(self::WORKER_RESULTS_GLOBAL) === '1') {
            $this->flushesWorkerResults = true;
            $this->resultsOnlyWrites = true;

            return $arguments;
        }

        $forceRebuild = $freshRequested && ($enabled || $recordingGlobal || $replayingGlobal);

        if (! $enabled && ! $this->forceRefetch && ! $recordingGlobal && ! $replayingGlobal) {
            $this->requestWorkerResults();

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

        if (Parallel::isWorker() && ($this->replayGraph instanceof Graph || $this->recordingActive || $this->flushesWorkerResults)) {
            $this->flushWorkerReplay();
        }

        if ($this->writesSuppressed || $this->resultsOnlyWrites || $this->hasUnfinishedTest()) {
            $this->recorder->reset();
            $this->coverageCollector->reset();

            return;
        }

        $recorder = $this->recorder;

        if (! $this->recordingActive && ! $recorder->isActive()) {
            return;
        }

        $this->graphWritten = true;

        $projectRoot = TestSuite::getInstance()->rootPath;
        $perTest = $this->piggybackCoverage
            ? $this->mergePerTestFiles($this->coverageCollector->perTestFiles(), $recorder->perTestFiles())
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

        $currentFingerprint = Fingerprint::compute($projectRoot);

        if ($this->structuralFingerprintShifted($currentFingerprint)) {
            $this->renderBadge('WARN', 'Project files changed during the run — discarding recorded edges.');
            $this->renderChild('Re-run --tia after your edits settle to record a fresh dependency graph.');
            $recorder->reset();
            $this->coverageCollector->reset();

            return;
        }

        $graph = $this->loadGraph($projectRoot) ?? new Graph($projectRoot);
        $graph->setFingerprint($currentFingerprint);
        $graph->setRecordedAtSha($this->branch, $currentSha);
        $graph->setLastRunTree(
            $this->branch,
            $changedFiles->snapshotTree($changedFiles->since($currentSha) ?? []),
        );
        $graph->replaceEdges($perTest, keepExisting: $this->piggybackCoverage);
        $graph->replaceTestTables($perTestTables);
        $graph->replaceTestInertiaComponents($perTestInertia);
        $graph->replaceJsFileToComponents(JsModuleGraph::build($projectRoot));

        $this->seedResultsInto($graph);

        if (! $this->saveGraph($graph)) {
            $this->renderBadge('ERROR', 'Could not write the dependency graph.');
            $recorder->reset();

            return;
        }

        $recorder->reset();
        $this->coverageCollector->reset();
    }

    public function addOutput(int $exitCode): int
    {
        if (Parallel::isWorker()) {
            return $exitCode;
        }

        if (Only::isEnabled() || $this->stoppedEarly() || $this->hasUnfinishedTest()) {
            $this->resultsOnlyWrites = true;
        }

        $this->reportMissingWorkerDrivers();

        if (Parallel::isEnabled()) {
            $this->mergeWorkerReplayPartials();
        }

        if ($this->writesSuppressed) {
            return $exitCode;
        }

        if ($this->resultsOnlyWrites) {
            $this->snapshotTestResults(complete: false);

            return $exitCode;
        }

        if ($this->replayRan || $this->graphUnreachable || $this->fullSuiteFallbackRan) {
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

        $currentFingerprint = Fingerprint::compute($projectRoot);

        if ($this->structuralFingerprintShifted($currentFingerprint)) {
            $this->renderBadge('WARN', 'Project files changed during the run — discarding recorded edges.');
            $this->renderChild('Re-run --tia after your edits settle to record a fresh dependency graph.');

            foreach ($partialKeys as $key) {
                $this->state->delete($key);
            }

            return $exitCode;
        }

        $graph = $this->loadGraph($projectRoot) ?? new Graph($projectRoot);
        $graph->setFingerprint($currentFingerprint);
        $graph->setRecordedAtSha($this->branch, $currentSha);
        $graph->setLastRunTree(
            $this->branch,
            $changedFiles->snapshotTree($changedFiles->since($currentSha) ?? []),
        );

        [$finalised, $finalisedTables, $finalisedInertia] = $this->consumePartials($partialKeys);

        if ($finalised === []) {
            if ($this->replayRan) {
                $this->snapshotTestResults();

                return $exitCode;
            }

            $this->renderBadge('ERROR', 'Recorded zero edges — coverage driver likely missing.');
            $this->renderChild('Install / enable pcov or xdebug (mode: coverage) in the worker PHP and retry.');

            return $exitCode;
        }

        $graph->replaceEdges($finalised, keepExisting: $this->piggybackCoverage);
        $graph->replaceTestTables($finalisedTables);
        $graph->replaceTestInertiaComponents($finalisedInertia);
        $graph->replaceJsFileToComponents(JsModuleGraph::build($projectRoot));

        if (! $this->saveGraph($graph)) {
            $this->renderBadge('ERROR', 'Could not write the dependency graph.');

            return $exitCode;
        }

        $this->snapshotTestResults(markKnownTestFiles: true);

        return $exitCode;
    }

    /**
     * @param  array{structural: array<string, mixed>, environmental: array<string, mixed>}  $current
     */
    private function reconcileFingerprint(Graph $graph, array $current): ?Graph
    {
        $stored = $graph->fingerprint();

        if (! Fingerprint::structuralMatches($stored, $current)) {
            $drift = Fingerprint::structuralDrift($stored, $current);

            $this->driftLabel = $this->formatStructuralDrift($drift);

            if (in_array('composer_lock', $drift, true)) {
                $branchSha = $graph->recordedAtSha($this->branch);
                if ($branchSha !== null) {
                    $summary = $this->composerLockDelta(
                        TestSuite::getInstance()->rootPath,
                        $branchSha,
                    );
                    if ($summary !== '') {
                        $this->driftDetails = $summary;
                    }
                }
            }

            $rebuilt = $this->tryRemoteBaselineForDrift($current);

            if ($rebuilt instanceof Graph) {
                return $this->reconcileFingerprint($rebuilt, $current);
            }

            $this->deleteState(self::KEY_GRAPH);
            $this->deleteState(self::KEY_COVERAGE_CACHE);

            return null;
        }

        $drift = Fingerprint::environmentalDrift($stored, $current);

        if ($drift !== []) {
            $this->renderBadge('WARN', sprintf(
                'Env differs from baseline (%s) — results dropped, edges reused.',
                implode(', ', $drift),
            ));

            $graph->clearResults($this->branch);
            $graph->setFingerprint($current);
            $this->saveGraph($graph);
            $this->deleteState(self::KEY_COVERAGE_CACHE);
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

        $subdirectoryPrefix = $this->gitSubdirectoryPrefix($projectRoot);

        if ($subdirectoryPrefix !== null) {
            Panic::with(new TiaRequiresRepositoryRoot($subdirectoryPrefix));
        }

        try {
            $this->resolveBranch($projectRoot);
        } catch (MissingDependency $missingGit) {
            $repository = new ChangedFiles($projectRoot);

            if ($repository->isRepository() && ! $repository->hasCommits()) {
                Panic::with(new TiaRequiresCommit);
            }

            throw $missingGit;
        }

        if (! $this->fallbackBranchResolved) {
            Panic::with(new ChangedFiles($projectRoot)->hasRemote()
                ? new TiaRequiresDefaultBranch
                : new TiaRequiresRemote);
        }

        $fingerprint = Fingerprint::compute($projectRoot);
        $this->startFingerprint = $fingerprint;

        if ($forceRebuild && ! $this->detachedHead) {
            Storage::purge($projectRoot);
        }

        $graph = ($forceRebuild || $this->forceRefetch) ? null : $this->loadGraph($projectRoot);

        if ($graph instanceof Graph) {
            $graph = $this->reconcileFingerprint($graph, $fingerprint);
        }

        if ($graph instanceof Graph) {
            $changedFiles = new ChangedFiles($projectRoot);
            $branchSha = $graph->recordedAtSha($this->branch);

            if ($branchSha !== null
                && $changedFiles->since($branchSha) === null) {
                $this->renderBadge('WARN', 'Recorded commit is no longer reachable — graph will be rebuilt.');
                $graph = null;
                $this->graphUnreachable = true;
            }
        }

        if (! $graph instanceof Graph
            && ! $forceRebuild
            && ! $this->baselineFetchAttemptedForDrift
            && $this->watchPatterns->isBaselined()
            && $this->baselineSync->fetchIfAvailable($projectRoot, $this->forceRefetch)) {
            $this->baselineFetchAttemptedForDrift = true;
            $graph = $this->loadGraph($projectRoot);
            if ($graph instanceof Graph) {
                $graph = $this->reconcileFingerprint($graph, $fingerprint);
            }
        }

        $coverageCacheOwned = $this->piggybackCoverage && $this->pestCoverageActive();

        if ($coverageCacheOwned) {
            $this->state->write(self::KEY_COVERAGE_MARKER, '');
        }

        if (! $graph instanceof Graph && $this->piggybackCoverage) {
            $this->emitCoverageScopedRecordSkipped();

            return $arguments;
        }

        if ($coverageCacheOwned && ! $this->state->exists(self::KEY_COVERAGE_CACHE)) {
            if ($this->driftLabel === null) {
                $this->freshGraphReason = 'recording a coverage baseline';
            }

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
        $this->resolveBranch($projectRoot);

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
            $this->recorder->activateLinkTracking();
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
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function activateWorkerRecorderForReplay(array $arguments): array
    {
        if ($this->piggybackCoverage) {
            $this->recorder->activateLinkTracking();
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

        $branchSha = $graph->recordedAtSha($this->branch);
        $changed = $changedFiles->since($branchSha) ?? [];

        $changed = $changedFiles->filterUnchangedSinceLastRun(
            $changed,
            $graph->lastRunTree($this->branch),
        );

        $hasProjectPhpSourceChanges = $this->hasProjectPhpSourceChanges($changed);
        $coverageAvailable = $this->piggybackCoverage || $this->recorder->driverAvailable();

        if ($hasProjectPhpSourceChanges && ! $coverageAvailable) {
            $this->fullSuiteFallbackRan = true;

            $this->renderBadge('WARN', 'Detected PHP source changes but no coverage driver is available.');
            $this->renderChild('Running the full suite to avoid using a stale dependency graph.');
            $this->renderChild('Install / enable pcov or xdebug (mode: coverage) so edges can be safely refreshed after PHP refactors.');

            return $arguments;
        }

        $affectedFromChanges = $changed === [] ? [] : $graph->testFilesOnDisk($graph->affected($changed));
        $rerunFromCache = [];

        if ($this->filteredMode && $graph->hasUnlocatedTestsToRerun($this->branch)) {
            $this->filteredMode = false;

            $this->renderBadge('WARN', 'Some cached tests due a re-run could not be located on disk.');
            $this->renderChild('Running the full suite with replay instead of a filtered run.');
        }

        if ($this->filteredMode) {
            $rerunFromCache = $graph->testFilesToRerun($this->branch);
        }

        $affected = array_values(array_unique([
            ...$affectedFromChanges,
            ...$rerunFromCache,
        ]));

        $this->reportAffectedSummary($changed, $affectedFromChanges, $rerunFromCache, $affected);

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
                if ($this->piggybackCoverage) {
                    $this->recorder->activateLinkTracking();
                } else {
                    $this->recorder->activate();
                }

                $this->recordingActive = true;
            }

            return $arguments;
        }

        if (! $this->persistAffectedSet($affected)) {
            $this->renderBadge('ERROR', 'Could not persist affected set — running full suite.');

            return $arguments;
        }

        $this->purgeWorkerPartials();

        Parallel::setGlobal(self::REPLAYING_GLOBAL, '1');

        if ($canRefreshReplayEdges) {
            Parallel::setGlobal(self::RECORDING_GLOBAL, '1');

            if ($this->piggybackCoverage) {
                Parallel::setGlobal(self::PIGGYBACK_COVERAGE_GLOBAL, '1');
            }
        }

        if ($this->filteredMode) {
            Parallel::setGlobal(self::FILTERED_GLOBAL, '1');
        }

        return $arguments;
    }

    /**
     * @param  array<int, string>  $changedFiles
     * @param  array<int, string>  $affectedFromChanges
     * @param  array<int, string>  $rerunFromCache
     * @param  array<int, string>  $affected
     */
    private function reportAffectedSummary(array $changedFiles, array $affectedFromChanges, array $rerunFromCache, array $affected): void
    {
        $this->output->writeln('');

        if ($affected === []) {
            $this->renderChild('Experimental TIA mode enabled.');

            return;
        }

        $newReruns = $rerunFromCache === []
            ? 0
            : count(array_diff($rerunFromCache, $affectedFromChanges));

        $reasons = [];
        $singleReason = (int) ($affectedFromChanges !== []) + (int) ($newReruns > 0) === 1;

        if ($affectedFromChanges !== []) {
            $reasons[] = $singleReason
                ? sprintf(
                    'from %d changed file%s',
                    count($changedFiles),
                    count($changedFiles) === 1 ? '' : 's',
                )
                : sprintf(
                    '%d from %d changed file%s',
                    count($affectedFromChanges),
                    count($changedFiles),
                    count($changedFiles) === 1 ? '' : 's',
                );
        }

        if ($newReruns > 0) {
            $reasons[] = $singleReason
                ? sprintf(
                    'from %d previously unsuccessful test%s',
                    $newReruns,
                    $newReruns === 1 ? '' : 's',
                )
                : sprintf(
                    '%d from previously unsuccessful test%s',
                    $newReruns,
                    $newReruns === 1 ? '' : 's',
                );
        }

        $this->renderChild(sprintf(
            'Experimental TIA mode enabled / %d affected test file%s%s.',
            count($affected),
            count($affected) === 1 ? '' : 's',
            $reasons === [] ? '' : ' ('.implode(', ', $reasons).')',
        ));

        $sorted = $affected;
        sort($sorted);

        $previewLimit = $this->output->isVerbose() ? count($sorted) : 10;
        $preview = array_slice($sorted, 0, $previewLimit);

        foreach ($preview as $file) {
            $this->output->writeln(sprintf('  <fg=gray>%s</>', $file));
        }

        $remainder = count($sorted) - count($preview);

        if ($remainder > 0) {
            $this->output->writeln(sprintf('  <fg=gray>… +%d more</>', $remainder));
        }
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

            $this->output->writeln('');
            $this->renderFreshGraph();

            return $arguments;
        }

        if ($this->piggybackCoverage) {
            $recorder->activateLinkTracking();
            $this->recordingActive = true;

            $this->output->writeln('');
            $this->renderFreshGraph();

            return $arguments;
        }

        $recorder->activate();
        $this->recordingActive = true;

        if ($this->driftLabel !== null || $this->freshGraphReason !== null) {
            $this->output->writeln('');
            $this->renderFreshGraph();

            return $arguments;
        }

        $this->renderChild('Running in TIA mode.');

        return $arguments;
    }

    private function renderFreshGraph(): void
    {
        if ($this->driftLabel === null && $this->freshGraphReason !== null) {
            $headline = sprintf('Experimental TIA mode enabled / %s.', $this->freshGraphReason);
        } else {
            $headline = 'Experimental TIA mode enabled / fresh graph';

            if ($this->driftLabel !== null) {
                $headline .= sprintf(' (%s changed)', $this->driftLabel);
            } else {
                $headline .= '.';
            }
        }

        $this->renderChild($headline);

        if ($this->driftDetails !== null) {
            foreach (explode(', ', $this->driftDetails) as $detail) {
                $this->output->writeln(sprintf('    <fg=gray>%s</>', $detail));
            }
        }
    }

    private function emitCoverageDriverMissing(): void
    {
        $this->output->writeln('');

        $this->renderChild('Running in TIA mode, however TIA is skipped as it needs ext-pcov or Xdebug.');
    }

    private function emitCoverageScopedRecordSkipped(): void
    {
        $this->output->writeln('');

        $this->renderChild('Running in TIA mode, however TIA is skipped as an active coverage report narrows the edges it could record.');
        $this->renderChild('Record the baseline with a plain --tia run first; coverage runs then reuse it.');
    }

    private function emitMutationScopedRecordSkipped(): void
    {
        $this->output->writeln('');

        $this->renderChild('Running in TIA mode, however TIA is skipped as mutation testing needs the coverage of a complete run.');
        $this->renderChild('Drop --mutate to narrow and replay with TIA.');
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

        $this->state->write(self::KEY_WORKER_EDGES_PREFIX.$this->workerToken().'.json', $json);
    }

    /**
     * @return list<string>
     */
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

        $this->renderBadge('WARN', sprintf(
            '%d worker(s) had no coverage driver — their per-test edges and results were dropped.',
            count($keys),
        ));
        $this->renderChild('Install / enable pcov or xdebug (mode: coverage) in the worker PHP and rerun.');
    }

    private function requestWorkerResults(): void
    {
        if (Parallel::isWorker() || ! Parallel::isEnabled() || $this->writesSuppressed) {
            return;
        }

        if ($this->state->read(self::KEY_GRAPH) === null) {
            return;
        }

        $this->purgeWorkerPartials();

        Parallel::setGlobal(self::WORKER_RESULTS_GLOBAL, '1');
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

        foreach ($results as $testId => $result) {
            $results[$testId] = $this->replayedAsRecorded($testId, $result);
        }

        $json = json_encode([
            'results' => $results,
            'replayed' => $this->replayedCount,
            'affected' => $this->affectedCount,
            'executed' => $this->executedCount,
            'truncated' => $this->stoppedEarly() || $collector->hasUnfinishedTest(),
        ], JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return;
        }

        $this->state->write(self::KEY_WORKER_RESULTS_PREFIX.$this->workerToken().'.json', $json);
    }

    /**
     * @return list<string>
     */
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

            if (($decoded['truncated'] ?? false) === true) {
                $this->resultsOnlyWrites = true;
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
     * @param  list<string>  $partialKeys
     * @return array{0: array<string, list<string>>, 1: array<string, list<string>>, 2: array<string, list<string>>}
     */
    private function consumePartials(array $partialKeys): array
    {
        $merged = ['files' => [], 'tables' => [], 'inertia' => []];

        foreach ($partialKeys as $key) {
            $data = $this->readPartial($key);

            if ($data === null) {
                $this->state->delete($key);

                continue;
            }

            foreach (['files', 'tables', 'inertia'] as $section) {
                foreach ($data[$section] as $testFile => $values) {
                    if (! isset($merged[$section][$testFile])) {
                        $merged[$section][$testFile] = [];
                    }

                    foreach ($values as $value) {
                        $merged[$section][$testFile][$value] = true;
                    }
                }
            }

            $this->state->delete($key);
        }

        return [
            array_map(array_keys(...), $merged['files']),
            array_map(array_keys(...), $merged['tables']),
            array_map(array_keys(...), $merged['inertia']),
        ];
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

    /**
     * @param  array<string, array<int, string>>  $coverage
     * @param  array<string, array<int, string>>  $linked
     * @return array<string, array<int, string>>
     */
    private function mergePerTestFiles(array $coverage, array $linked): array
    {
        foreach ($linked as $testFile => $sources) {
            $existing = $coverage[$testFile] ?? [];

            $coverage[$testFile] = array_values(array_unique([...$existing, ...$sources]));
        }

        return $coverage;
    }

    private function resultTime(string $testId, float $time): float
    {
        return $this->cachedTimeByTestId[$testId] ?? $time;
    }

    /**
     * @param  array{status: int, message: string, time: float, assertions: int, file?: string}  $result
     * @return array{status: int, message: string, time: float, assertions: int, file?: string}
     */
    private function replayedAsRecorded(string $testId, array $result): array
    {
        $result['time'] = $this->resultTime($testId, $result['time']);

        $cached = $this->cachedStatusByTestId[$testId] ?? null;

        if ($cached !== null) {
            $result['status'] = $cached['status'];
            $result['message'] = $cached['message'];
        }

        return $result;
    }

    private function seedResultsInto(Graph $graph): void
    {
        /** @var ResultCollector $collector */
        $collector = Container::getInstance()->get(ResultCollector::class);

        $results = $collector->all();
        $touchedFiles = [];

        foreach ($results as $testId => $result) {
            $file = $result['file'] ?? null;

            if ($file === null || str_contains($file, "eval()'d")) {
                $file = $this->resolveFailedTestFile($testId);
            }

            if (is_string($file) && $file !== '') {
                $touchedFiles[$file] = true;
            }

            $result = $this->replayedAsRecorded($testId, $result);

            $graph->setResult(
                $this->branch,
                $testId,
                $result['status'],
                $result['message'],
                $result['time'],
                $result['assertions'],
                $file,
            );
        }

        $graph->markKnownTestFiles(array_keys($touchedFiles));
        $graph->pruneStaleResults($this->branch, array_keys($touchedFiles), array_keys($results));
        $this->reclaim($graph);

        $collector->reset();
    }

    private function reclaim(Graph $graph): void
    {
        if ($this->branch !== $this->fallbackBranch) {
            $graph->markBaselineComplete($this->branch);
        }

        $graph->pruneMissingTests();
        $graph->pruneResultsForMissingFiles($this->branch);

        $branches = new ChangedFiles(TestSuite::getInstance()->rootPath)->branchNames();

        if ($branches === null) {
            return;
        }

        if (! in_array($this->fallbackBranch, $branches, true)) {
            return;
        }

        $graph->pruneMissingBranches([...$branches, $this->branch, $this->fallbackBranch]);
    }

    private function snapshotTestResults(bool $markKnownTestFiles = false, bool $complete = true): void
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

        try {
            $this->resolveBranch($projectRoot);
        } catch (MissingDependency) {
        }

        $graph->setFallbackBranch($this->fallbackBranch);

        $touchedFiles = [];

        $recordsEdges = $complete && ($markKnownTestFiles || $this->recordingActive);

        foreach ($results as $testId => $result) {
            $file = $result['file'] ?? null;

            if ($file === null || str_contains($file, "eval()'d")) {
                $file = $this->resolveFailedTestFile($testId);
            }

            if (is_string($file) && $file !== '') {
                $touchedFiles[$file] = true;
            }

            if (! $recordsEdges && (! is_string($file) || ! $graph->knowsTest($file))) {
                continue;
            }

            $result = $this->replayedAsRecorded($testId, $result);

            $graph->setResult(
                $this->branch,
                $testId,
                $result['status'],
                $result['message'],
                $result['time'],
                $result['assertions'],
                $file,
            );
        }

        if ($markKnownTestFiles) {
            $graph->markKnownTestFiles(array_keys($touchedFiles));
        }

        if ($complete) {
            $graph->pruneStaleResults($this->branch, array_keys($touchedFiles), array_keys($results));
            $this->reclaim($graph);
        }

        $this->saveGraph($graph);
        $collector->reset();
    }

    private function resolveFailedTestFile(string $testId): ?string
    {
        $class = strstr($testId, '::', true);

        if (! is_string($class) || $class === '' || ! class_exists($class)) {
            return null;
        }

        assert(property_exists($class, '__filename') && is_string($class::$__filename));

        $filename = $class::$__filename;

        if ($filename !== '' && ! str_contains($filename, "eval()'d")) {
            return $filename;
        }

        $current = new \ReflectionClass($class);

        while ($current !== false) {
            $file = $current->getFileName();

            if ($file !== false && ! str_contains($file, "eval()'d")) {
                return $file;
            }

            $current = $current->getParentClass();
        }

        return null;
    }

    private function coverageReportActive(): bool
    {
        if ($this->pestCoverageActive()) {
            return true;
        }

        return array_any(self::COVERAGE_REPORT_FLAGS, fn (string $flag): bool => $this->hasArgument($flag, $this->originalArguments));
    }

    private function pestCoverageActive(): bool
    {
        $coverage = Container::getInstance()->get(Coverage::class);
        assert($coverage instanceof Coverage);

        return $coverage->coverage;
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function guardUnsupportedOptions(array $arguments): void
    {
        foreach (self::UNSUPPORTED_OPTIONS as $option) {
            if (! $this->hasArgument($option, $arguments) && ! $this->hasArgument($option, $this->originalArguments)) {
                continue;
            }

            Panic::with(new InvalidOption(sprintf(
                'The [%s] option cannot be combined with [%s].',
                $option,
                self::OPTION,
            )));
        }
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function hasPartialSelection(array $arguments): bool
    {
        foreach (self::PARTIAL_SELECTION_FLAGS as $flag) {
            if ($this->hasArgument($flag, $arguments)) {
                return true;
            }

            if ($this->hasArgument($flag, $this->originalArguments)) {
                return true;
            }
        }

        return false;
    }

    private function stoppedEarly(): bool
    {
        return TestResultFacade::shouldStop();
    }

    private function hasUnfinishedTest(): bool
    {
        $collector = Container::getInstance()->get(ResultCollector::class);
        assert($collector instanceof ResultCollector);

        return $collector->hasUnfinishedTest();
    }

    private function resolveBranch(string $projectRoot): void
    {
        if ($this->branchResolved) {
            return;
        }

        $this->branchResolved = true;

        $changedFiles = new ChangedFiles($projectRoot);

        $resolved = $this->resolveFallbackBranch($changedFiles);

        $this->fallbackBranchResolved = $resolved !== null;
        $this->fallbackBranch = $resolved ?? self::DEFAULT_BRANCH;

        Parallel::setGlobal(self::FALLBACK_BRANCH_GLOBAL, $this->fallbackBranch);

        $currentBranch = $changedFiles->currentBranch();

        $this->detachedHead = $currentBranch === null;
        $this->branch = $currentBranch ?? $this->fallbackBranch;
    }

    private function resolveFallbackBranch(ChangedFiles $changedFiles): ?string
    {
        $inherited = Parallel::getGlobal(self::FALLBACK_BRANCH_GLOBAL);

        if (is_string($inherited) && $inherited !== '') {
            return $inherited;
        }

        return $this->watchPatterns->defaultBranch()
            ?? CiDefaultBranch::detect()
            ?? $changedFiles->defaultBranch()
            ?? $this->soleRecordedBranch();
    }

    private function soleRecordedBranch(): ?string
    {
        $json = $this->state->read(self::KEY_GRAPH);

        if ($json === null) {
            return null;
        }

        $branches = Graph::branchesIn($json);

        return count($branches) === 1 ? $branches[0] : null;
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function hasExplicitPathArgument(array $arguments): bool
    {
        $projectRoot = TestSuite::getInstance()->rootPath;
        $testPaths = SourceScope::testPaths();

        if ($testPaths === []) {
            return false;
        }

        foreach ($arguments as $index => $arg) {
            $arg = (string) $arg; // @phpstan-ignore-line

            if ($arg === '') {
                continue;
            }
            if (str_starts_with($arg, '-')) {
                continue;
            }

            if ($index === 0) {
                continue;
            }

            $previous = $arguments[$index - 1] ?? '';

            if (in_array($previous, self::VALUE_TAKING_FLAGS, true)) {
                continue;
            }

            $candidate = $this->resolveArgumentPath($arg, $projectRoot);

            if ($candidate === null) {
                continue;
            }

            if ($this->narrowsSuite($candidate, $testPaths)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $testPaths
     */
    private function narrowsSuite(string $candidate, array $testPaths): bool
    {
        foreach ($testPaths as $testPath) {
            if ($candidate === $testPath || str_starts_with($candidate, $testPath.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return array_all($testPaths, fn (string $testPath): bool => ! str_starts_with($testPath, $candidate.DIRECTORY_SEPARATOR));
    }

    private function resolveArgumentPath(string $arg, string $projectRoot): ?string
    {
        $candidates = [$arg, rtrim($projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($arg, DIRECTORY_SEPARATOR)];

        foreach ($candidates as $candidate) {
            if (! is_file($candidate) && ! is_dir($candidate)) {
                continue;
            }

            $real = @realpath($candidate);

            return rtrim($real === false ? $candidate : $real, '/\\');
        }

        return null;
    }

    /**
     * @param  array<int, string>  $changedFiles
     */
    private function hasProjectPhpSourceChanges(array $changedFiles): bool
    {
        foreach ($changedFiles as $rel) {
            if (! str_ends_with($rel, '.php')) {
                continue;
            }

            if (str_ends_with($rel, '.blade.php')) {
                continue;
            }
            if (str_starts_with($rel, 'tests/')) {
                continue;
            }
            if (str_starts_with($rel, 'vendor/')) {
                continue;
            }
            if (str_starts_with($rel, 'storage/framework/')) {
                continue;
            }
            if (str_starts_with($rel, 'bootstrap/cache/')) {
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

        if (! $this->watchPatterns->isBaselined()) {
            return null;
        }

        if (! $this->baselineSync->fetchIfAvailable($projectRoot, $this->forceRefetch, hasAnchor: true)) {
            return null;
        }

        $fetched = $this->loadGraph($projectRoot);

        if (! $fetched instanceof Graph) {
            return null;
        }

        if (! Fingerprint::structuralMatches($fetched->fingerprint(), $current)) {
            $this->output->writeln('  <fg=gray>  However, baseline still drifts — discarding.</>');

            return null;
        }

        $this->renderBadge('SUCCESS', 'Fetched baseline matches — skipping local rebuild.');

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

    private function gitSubdirectoryPrefix(string $projectRoot): ?string
    {
        return new Git($projectRoot)->subdirectoryPrefix();
    }

    private function composerLockDelta(string $projectRoot, string $sha): string
    {
        $current = @file_get_contents($projectRoot.'/composer.lock');
        if ($current === false) {
            return '';
        }

        $baseline = new Git($projectRoot)->show($sha, 'composer.lock');

        if ($baseline === null) {
            return '';
        }

        $oldVersions = $this->lockVersions($baseline);
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
