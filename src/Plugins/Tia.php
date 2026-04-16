<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\AfterEachable;
use Pest\Contracts\Plugins\BeforeEachable;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Contracts\Plugins\Runnable;
use Pest\Contracts\Plugins\Terminable;
use Pest\Exceptions\NoDirtyTestsFound;
use Pest\Panic;
use Pest\Support\Container;
use Pest\Plugins\Tia\ChangedFiles;
use Pest\Plugins\Tia\Fingerprint;
use Pest\Plugins\Tia\Graph;
use Pest\Plugins\Tia\Recorder;
use Pest\Plugins\Tia\State;
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
 *    `test → [source_file, …]` edges land in `.pest/cache/tia.json`.
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
 *    into `.pest/cache/tia-worker-<TEST_TOKEN>.json`.
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
final class Tia implements AddsOutput, AfterEachable, BeforeEachable, HandlesArguments, Runnable, Terminable
{
    use Concerns\HandleArguments;

    private const string OPTION = '--tia';

    private const string REBUILD_OPTION = '--tia-rebuild';

    private const string CACHE_PATH = '.pest/cache/tia.json';

    private const string AFFECTED_PATH = '.pest/cache/tia-affected.json';

    private const string WORKER_CACHE_PREFIX = '.pest/cache/tia-worker-';

    /**
     * Global flag toggled by the parent process so workers know to record.
     */
    private const string RECORDING_GLOBAL = 'TIA_RECORDING';

    /**
     * Global flag that tells workers to install the TIA filter (replay mode).
     * Workers read the affected set from `.pest/cache/tia-affected.json`.
     */
    private const string REPLAYING_GLOBAL = 'TIA_REPLAYING';

    private bool $graphWritten = false;

    public function __construct(private readonly OutputInterface $output) {}

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

    public function beforeEach(string $filename, string $testId): bool
    {
        return ! State::instance()->shouldReplayFromCache($filename, $testId);
    }

    public function run(string $filename, string $testId): bool
    {
        return ! State::instance()->shouldReplayFromCache($filename, $testId);
    }

    public function afterEach(string $filename, string $testId): bool
    {
        return ! State::instance()->shouldReplayFromCache($filename, $testId);
    }

    public function terminate(): void
    {
        if ($this->graphWritten) {
            return;
        }

        $recorder = Recorder::instance();

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
        $cachePath = $projectRoot.DIRECTORY_SEPARATOR.self::CACHE_PATH;

        $graph = Graph::load($projectRoot, $cachePath) ?? new Graph($projectRoot);
        $graph->setFingerprint(Fingerprint::compute($projectRoot));
        $graph->setRecordedAtSha((new ChangedFiles($projectRoot))->currentSha());
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
            self::CACHE_PATH,
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

        if ((string) Parallel::getGlobal(self::RECORDING_GLOBAL) !== '1') {
            return $exitCode;
        }

        $projectRoot = TestSuite::getInstance()->rootPath;
        $partials = $this->collectWorkerPartials($projectRoot);

        if ($partials === []) {
            return $exitCode;
        }

        $cachePath = $projectRoot.DIRECTORY_SEPARATOR.self::CACHE_PATH;

        $graph = Graph::load($projectRoot, $cachePath) ?? new Graph($projectRoot);
        $graph->setFingerprint(Fingerprint::compute($projectRoot));
        $graph->setRecordedAtSha((new ChangedFiles($projectRoot))->currentSha());

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
            self::CACHE_PATH,
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
        WatchPatterns::instance()->useDefaults($projectRoot);

        $cachePath = $projectRoot.DIRECTORY_SEPARATOR.self::CACHE_PATH;
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

            if ($changedFiles->gitAvailable()
                && $graph->recordedAtSha() !== null
                && $changedFiles->since($graph->recordedAtSha()) === null) {
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

        $recorder = Recorder::instance();

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
        $cachePath = $projectRoot.DIRECTORY_SEPARATOR.self::CACHE_PATH;
        $affectedPath = $projectRoot.DIRECTORY_SEPARATOR.self::AFFECTED_PATH;

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

        State::instance()->activate(
            $projectRoot,
            $graph,
            $affectedSet,
            $this->loadPreviousDefects($projectRoot),
        );
    }

    /**
     * Reads PHPUnit's own result cache and returns the test ids that failed
     * or errored in the previous run. These are excluded from replay so the
     * user sees current state rather than a stale pass.
     *
     * @return array<string, true>
     */
    private function loadPreviousDefects(string $projectRoot): array
    {
        // PHPUnit writes the cache under either `<projectRoot>/.phpunit.result.cache`
        // (legacy) or `<cacheDirectory>/test-results`. Pest's Cache plugin
        // additionally defaults `cacheDirectory` to
        // `vendor/pestphp/pest/.temp` when the user hasn't configured one.
        // We probe the common locations; if we miss the file, replay falls
        // back to its safe default (still runs the test).
        $candidates = [
            $projectRoot.'/.phpunit.result.cache',
            $projectRoot.'/.phpunit.cache/test-results',
            $projectRoot.'/.pest/cache/test-results',
            $projectRoot.'/vendor/pestphp/pest/.temp/test-results',
        ];

        $path = null;

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $path = $candidate;

                break;
            }
        }

        if ($path === null) {
            return [];
        }

        $raw = @file_get_contents($path);

        if ($raw === false) {
            return [];
        }

        $data = json_decode($raw, true);

        if (! is_array($data) || ! isset($data['defects']) || ! is_array($data['defects'])) {
            return [];
        }

        $out = [];

        foreach ($data['defects'] as $id => $_status) {
            if (is_string($id)) {
                $out[$id] = true;
            }
        }

        return $out;
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

        $changed = $changedFiles->since($graph->recordedAtSha()) ?? [];

        // Even with zero changes, we still run through the suite so the user
        // sees the previous results reflected (cached passes replay as
        // instant passes; failures re-run to surface current state). This
        // matches the UX of test runners like NCrunch where every run
        // produces a full report regardless of what actually executed.
        $affected = $changed === [] ? [] : $graph->affected($changed);

        $testSuite = TestSuite::getInstance();

        if (! Parallel::isEnabled()) {
            // Series mode: activate replay state. Tests still appear in the
            // run (correct counts, coverage aggregation, event timeline);
            // unaffected ones short-circuit inside `Testable::__runTest`
            // and replay their previous passing status.
            $affectedSet = array_fill_keys($affected, true);

            State::instance()->activate(
                $projectRoot,
                $graph,
                $affectedSet,
                $this->loadPreviousDefects($projectRoot),
            );

            $this->output->writeln(sprintf(
                '  <fg=green>TIA</> %d changed file(s) → %d affected, remaining tests replay cached result.',
                count($changed),
                count($affected),
            ));

            return $arguments;
        }

        // Parallel mode. Paratest's CLI only accepts a single positional
        // `<path>`, so we cannot pass the affected set as multiple args.
        // Instead, persist the affected set to a cache file and flip a
        // global that tells each worker to install the TIA filter on boot.
        //
        // Cost trade-off: each worker still discovers the full test tree,
        // but the filter drops unaffected tests before they ever run. Narrow
        // CLI handoff would be ideal; it requires generating a temporary
        // phpunit.xml and is out of scope for the MVP.
        if (! $this->persistAffectedSet($projectRoot, $affected)) {
            $this->output->writeln(
                '  <fg=red>TIA</> failed to persist affected set — running full suite.',
            );

            return $arguments;
        }

        Parallel::setGlobal(self::REPLAYING_GLOBAL, '1');

        $this->output->writeln(sprintf(
            '  <fg=green>TIA</> %d changed file(s) → %d affected, remaining tests replay cached result (parallel).',
            count($changed),
            count($affected),
        ));

        return $arguments;
    }

    /**
     * @param  array<int, string>  $affected  Project-relative paths.
     */
    private function persistAffectedSet(string $projectRoot, array $affected): bool
    {
        $path = $projectRoot.DIRECTORY_SEPARATOR.self::AFFECTED_PATH;
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

        $recorder = Recorder::instance();

        if (! $recorder->driverAvailable()) {
            $this->output->writeln(
                '  <fg=red>TIA</> No coverage driver is available. '.
                'Install ext-pcov or enable Xdebug in coverage mode, then rerun with `--tia`.',
            );

            return $arguments;
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

        $path = $projectRoot.DIRECTORY_SEPARATOR.self::WORKER_CACHE_PREFIX.$token.'.json';
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
        $pattern = $projectRoot.DIRECTORY_SEPARATOR.self::WORKER_CACHE_PREFIX.'*.json';
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
            if (! is_string($test) || ! is_array($sources)) {
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

    private function coverageReportActive(): bool
    {
        try {
            /** @var Coverage $coverage */
            $coverage = Container::getInstance()->get(Coverage::class);
        } catch (Throwable) {
            return false;
        }

        return property_exists($coverage, 'coverage') && $coverage->coverage === true;
    }
}
