<?php

declare(strict_types=1);

namespace Pest\Plugins;

use NunoMaduro\Collision\Adapters\Phpunit\Printers\DefaultPrinter;
use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Contracts\Plugins\HandlesOriginalArguments;
use Pest\Contracts\Plugins\Terminable;
use Pest\Exceptions\MissingDependency;
use Pest\Exceptions\NoAffectedTestsFound;
use Pest\Exceptions\TiaRequiresRepositoryRoot;
use Pest\Panic;
use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Tia\BaselineSync;
use Pest\Plugins\Tia\ChangedFiles;
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
use Pest\Support\View;
use Pest\TestCaseFilters\TiaTestCaseFilter;
use Pest\TestSuite;
use PHPUnit\Framework\TestStatus\TestStatus;
use PHPUnit\TestRunner\TestResult\Facade as TestResultFacade;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

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

    /**
     * Set by the mutation plugin on the subprocess running a single mutant,
     * and nowhere else. Its own `--mutate` flag is popped before the argv is
     * handed to that subprocess, so the flag cannot be matched instead.
     */
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

    private const string PIGGYBACK_COVERAGE_GLOBAL = 'TIA_PIGGYBACK_COVERAGE';

    /**
     * PHPUnit/Pest CLI flags whose subsequent argument is a value, not a path.
     *
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
        '--coverage-html', '--coverage-php', '--coverage-text', '--coverage-xml',
        '--coverage-filter', '--path-coverage',
        '--repeat', '--retry-times', '--memory-limit', '--seed',
        '--compact', '--ci-build-id', '--min',
    ];

    /**
     * Flags that narrow this run to a subset of the suite.
     *
     * Only user-supplied, per-run narrowing belongs here. A filter that is
     * always in force — `<groups>` in phpunit.xml, or a plugin registering a
     * test case filter from `boot()` — applies equally to the runs that build
     * the baseline, so it does not make this run narrower than the baseline
     * and must not disable baseline writes.
     *
     * `--shard` is rewritten to `--filter` before this plugin sees the
     * arguments, so it is covered here too. The `bin/pest`-only flags are
     * stripped from the handled arguments, so they are matched against the
     * original argv instead.
     *
     * Flags that cut a run short instead of narrowing it — `--bail`, `--retry`,
     * `--stop-on-*` — do not belong here either. They only narrow the run when
     * something actually fails, and that is not known until it is over, so they
     * are handled by stoppedEarly() from addOutput().
     *
     * @var list<string>
     */
    private const array PARTIAL_SELECTION_FLAGS = [
        '--filter', '--exclude-filter', '--group', '--exclude-group',
        '--covers', '--uses', '--testsuite', '--exclude-testsuite', '--test-suffix',
        '--dirty', '--todo', '--todos', '--flaky', '--notes',
        '--assignee', '--issue', '--ticket', '--pr', '--pull-request',
    ];

    private bool $graphWritten = false;

    private bool $replayRan = false;

    private int $replayedCount = 0;

    private int $affectedCount = 0;

    private int $executedCount = 0;

    /** @var array<string, int> */
    private array $cachedAssertionsByTestId = [];

    private ?Graph $replayGraph = null;

    /**
     * The baseline this run reads from and writes to.
     *
     * `main` is only the fallback for a repository whose branch cannot be read
     * — it is also the branch every other baseline falls back to reading, so
     * writing there by accident corrupts the shared baseline. Resolved through
     * resolveBranch() rather than at every use site, because the git call it
     * needs is not free.
     */
    private string $branch = 'main';

    private bool $branchResolved = false;

    /** @var array<string, true> */
    private array $affectedFiles = [];

    /** @var array{structural: array<string, mixed>, environmental: array<string, mixed>}|null */
    private ?array $startFingerprint = null;

    private bool $piggybackCoverage = false;

    private bool $recordingActive = false;

    private bool $forceRefetch = false;

    private bool $baselineFetchAttemptedForDrift = false;

    private bool $freshRebuild = false;

    private bool $filteredMode = false;

    /**
     * Bars this run from touching the graph at all, results included.
     *
     * Reserved for runs whose results describe something other than the code
     * in the working tree, which is nothing the baseline can ever use.
     */
    private bool $writesSuppressed = false;

    /**
     * Narrows this run's writes to the results of the tests it actually ran.
     *
     * A run that covered only part of the suite still learns something true
     * about the tests it did reach. What it cannot do is speak for the rest:
     * pruning results, advancing the recorded sha and replacing the edge map
     * all claim the whole suite reported, so they stay behind a complete run.
     */
    private bool $resultsOnlyWrites = false;

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
     * use from static contexts — matches both `--flag` and `--flag=value`.
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
            $assertions = $this->replayGraph->getAssertions($this->branch, $testId);
            $this->cachedAssertionsByTestId[$testId] = $assertions ?? 0;
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
        $hasExplicitPath = $this->hasExplicitPathArgument($arguments);
        $partial = ! $isWorker && ($hasExplicitPath || $this->hasPartialSelection($arguments));
        $disabled = $disabled || $partial;

        // A mutation subprocess runs the suite against source the mutation
        // plugin has deliberately broken. Its failures describe the mutant, not
        // the working tree, so unlike every other narrowed run there is nothing
        // in its results worth keeping. The parent `--mutate` run is untouched
        // by this: it runs the whole suite against real source.
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
            if ($partial) {
                // TIA cannot choose what runs here — the user already did — but
                // the tests they picked still report honestly, so their results
                // are kept and everything that would speak for the excluded ones
                // is not. `--no-tia` needs none of this: it still runs the whole
                // suite, so it remains a complete run.
                $this->resultsOnlyWrites = true;

                // `$this->filteredMode` counts as asking for it: reaching here
                // means the narrowing came from the command line while filtered
                // mode came from the environment or the config, and a run that
                // silently declines what the config asked for is the one most
                // in need of the explanation.
                if ($cliEnabled || $freshRequested || $this->forceRefetch || $this->filteredMode) {
                    $this->output->writeln('');
                    $this->renderChild('TIA does not apply to partial runs — running the selected tests directly.');
                }
            }

            $this->forceRefetch = false;
            $this->filteredMode = false;
            $this->freshRebuild = false;

            return $arguments;
        }

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

        // Both only ever set for the parent — addOutput() returns early in
        // workers, whose partials are ephemeral and only reach the baseline if
        // the parent consumes them. Everything this method goes on to write is
        // whole-suite by nature — the edge map above all — so a narrowed run
        // stops here too, its results already persisted by addOutput().
        if ($this->writesSuppressed || $this->resultsOnlyWrites) {
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
        $graph->replaceEdges($perTest);
        $graph->replaceTestTables($perTestTables);
        $graph->replaceTestInertiaComponents($perTestInertia);
        $graph->replaceJsFileToComponents(JsModuleGraph::build($projectRoot));

        if ($this->freshRebuild) {
            $graph->pruneMissingTests();
        }

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

        // `->only()` narrows the executed set exactly like `--filter` does, but
        // is only knowable once the suite has been collected — too late to turn
        // TIA off up front. Sampled in addOutput() because Only's lock file is
        // already gone by the time terminate() runs (its plugin terminates
        // first). Whether the run was cut short is likewise only knowable now.
        if (Only::isEnabled() || $this->stoppedEarly()) {
            $this->resultsOnlyWrites = true;
        }

        $this->reportMissingWorkerDrivers();

        // Runs before the checks below: it is what fills the parent's result
        // collector in parallel, and a worker that stopped early narrows the
        // whole run.
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

        $graph->replaceEdges($finalised);
        $graph->replaceTestTables($finalisedTables);
        $graph->replaceTestInertiaComponents($finalisedInertia);
        $graph->replaceJsFileToComponents(JsModuleGraph::build($projectRoot));

        if ($this->freshRebuild) {
            $graph->pruneMissingTests();
        }

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

            $this->state->delete(self::KEY_GRAPH);
            $this->state->delete(self::KEY_COVERAGE_CACHE);

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

        $subdirectoryPrefix = $this->gitSubdirectoryPrefix($projectRoot);

        if ($subdirectoryPrefix !== null) {
            Panic::with(new TiaRequiresRepositoryRoot($subdirectoryPrefix));
        }

        $this->resolveBranch($projectRoot);

        $fingerprint = Fingerprint::compute($projectRoot);
        $this->startFingerprint = $fingerprint;

        if ($forceRebuild) {
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

        if ($this->piggybackCoverage) {
            $this->state->write(self::KEY_COVERAGE_MARKER, '');
        }

        if ($this->piggybackCoverage && ! $this->state->exists(self::KEY_COVERAGE_CACHE)) {
            if ($graph instanceof Graph && $this->driftLabel === null) {
                $this->freshGraphReason = 'recording coverage baseline';
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
            $this->renderBadge('WARN', 'Detected PHP source changes but no coverage driver is available.');
            $this->renderChild('Running the full suite to avoid using a stale dependency graph.');
            $this->renderChild('Install / enable pcov or xdebug (mode: coverage) so edges can be safely refreshed after PHP refactors.');

            return $arguments;
        }

        $affectedFromChanges = $changed === [] ? [] : $graph->affected($changed);
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
                $this->recorder->activate();
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

        $this->renderChild('Running in TIA mode.');

        return $arguments;
    }

    private function renderFreshGraph(): void
    {
        $headline = 'Experimental TIA mode enabled / fresh graph';

        if ($this->driftLabel !== null) {
            $headline .= sprintf(' (%s changed)', $this->driftLabel);
        } elseif ($this->freshGraphReason !== null) {
            $headline .= sprintf(' (%s)', $this->freshGraphReason);
        } else {
            $headline .= '.';
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
            // Only the worker knows it stopped early — the parent runs no tests
            // of its own, so its own check would always come back clean.
            'truncated' => $this->stoppedEarly(),
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

            // One worker stopping early leaves the whole suite incomplete: the
            // tests it never reached are missing from the merged result set just
            // as if they had been filtered out.
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
     * Union of two per-test edge maps — piggybacked line-coverage edges plus
     * the recorder's link-tracked edges (rendered Blade views, ...), which
     * never appear in line coverage.
     *
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

        $collector->reset();
    }

    /**
     * Folds the run's results into the existing graph.
     *
     * An incomplete run passes `$complete: false`, which keeps the additive
     * half — the results of the tests it did run — and drops the half that
     * speaks for the suite as a whole.
     */
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
            // This run never asked for TIA, so a missing git must not turn it
            // into a failure the way it does on the TIA path. Writing to the
            // fallback baseline is the lesser of the two evils.
        }

        $touchedFiles = [];

        foreach ($results as $testId => $result) {
            $file = $result['file'] ?? null;

            if ($file === null || str_contains($file, "eval()'d")) {
                $file = $this->resolveFailedTestFile($testId);
            }

            if (is_string($file) && $file !== '') {
                $touchedFiles[$file] = true;
            }

            // A result is only ever invalidated through the edges of the test
            // that produced it, so one recorded for a test the graph has no
            // edges for could never be invalidated again — it would be replayed
            // as settled however far the code around it moved. Only a complete
            // run records the edges that would close that gap, so until one
            // does, the test stays unknown.
            if (! $complete && (! is_string($file) || ! $graph->knowsTest($file))) {
                continue;
            }

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

        // Pruning reads the absence of a test from this run as the test being
        // gone. That only holds if every test was invited to report.
        if ($complete) {
            $graph->pruneStaleResults($this->branch, array_keys($touchedFiles), array_keys($results));
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
        $coverage = Container::getInstance()->get(Coverage::class);
        assert($coverage instanceof Coverage);

        return $coverage->coverage;
    }

    /**
     * Whether a selection-narrowing flag was given, either among the arguments
     * PHPUnit receives or — for the flags `bin/pest` consumes itself — among
     * the original argv. Explicit path arguments and `->only()` are detected
     * separately.
     *
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

    /**
     * Whether the run stopped before reaching every test it had queued.
     *
     * Covers `--bail`, `--retry` and every `--stop-on-*` flag, the equivalent
     * `phpunit.xml` attributes, and an interrupted run — none of which narrow
     * the selection up front, so hasPartialSelection() cannot see them. The
     * tests queued behind the defect that halted the run never reported, and
     * folding what did report into the baseline prunes the cached results of
     * their siblings in every file the run had already entered.
     *
     * Deliberately unguarded. Both callers run only once PHPUnit's
     * configuration is registered — the kernel reads it unguarded itself just
     * before dispatching addOutput(), and flushWorkerReplay() bails out unless
     * the worker actually executed something. Swallowing a failure here would
     * report every truncated run as complete, which is the corruption this
     * guards against in the first place.
     */
    private function stoppedEarly(): bool
    {
        return TestResultFacade::shouldStop();
    }

    /**
     * Resolves the baseline this run reads from and writes to, once.
     *
     * Results are written on runs where TIA itself took no part, and those
     * never reach handleParent(). Without this the default would stand and
     * every such run would write its results to `main`, whatever branch it
     * actually ran on.
     */
    private function resolveBranch(string $projectRoot): void
    {
        if ($this->branchResolved) {
            return;
        }

        $this->branchResolved = true;

        $this->branch = new ChangedFiles($projectRoot)->currentBranch() ?? $this->branch;
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
            if ($index > 0) {
                $previous = $arguments[$index - 1] ?? '';
                if (in_array($previous, self::VALUE_TAKING_FLAGS, true)) {
                    continue;
                }
            }

            $candidate = $this->resolveArgumentPath($arg, $projectRoot);

            if ($candidate === null) {
                continue;
            }

            foreach ($testPaths as $testPath) {
                if ($candidate === $testPath || str_starts_with($candidate, $testPath.DIRECTORY_SEPARATOR)) {
                    return true;
                }
            }
        }

        return false;
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

    /**
     * The path from the git repository root down to $projectRoot (e.g.
     * `laravel-app`) when the project is nested inside a larger repo, or `null`
     * when the project root is itself the repo root (or git is unavailable).
     * TIA requires the two to coincide: git reports and addresses paths
     * relative to the repo root, while the dependency graph is project-relative.
     */
    private function gitSubdirectoryPrefix(string $projectRoot): ?string
    {
        $process = new Process(['git', 'rev-parse', '--show-prefix'], $projectRoot);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $prefix = trim($process->getOutput());

        return $prefix === '' ? null : rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $prefix), '/');
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
