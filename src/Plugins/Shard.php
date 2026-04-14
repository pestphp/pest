<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Contracts\Plugins\Terminable;
use Pest\Exceptions\InvalidOption;
use Pest\Subscribers\EnsureShardTimingFinished;
use Pest\Subscribers\EnsureShardTimingsAreCollected;
use Pest\Subscribers\EnsureShardTimingStarted;
use Pest\TestSuite;
use PHPUnit\Event;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
final class Shard implements AddsOutput, HandlesArguments, Terminable
{
    use Concerns\HandleArguments;

    private const string SHARD_OPTION = 'shard';

    /**
     * The shard index and total number of shards.
     *
     * @var array{
     *     index: int,
     *     total: int,
     *     testsRan: int,
     *     testsCount: int
     * }|null
     */
    private static ?array $shard = null;

    /**
     * Whether to update the shards.json file.
     */
    private static bool $updateShards = false;

    /**
     * Whether time-balanced sharding was used.
     */
    private static bool $timeBalanced = false;

    /**
     * Whether the shards.json file is outdated.
     */
    private static bool $shardsOutdated = false;

    /**
     * Whether the test suite passed.
     */
    private static bool $passed = false;

    /**
     * Collected timings from workers or subscribers.
     *
     * @var array<string, float>|null
     */
    private static ?array $collectedTimings = null;

    /**
     * The canonical list of test classes from --list-tests.
     *
     * @var list<string>|null
     */
    private static ?array $knownTests = null;

    /**
     * Creates a new Plugin instance.
     */
    public function __construct(
        private readonly OutputInterface $output,
    ) {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function handleArguments(array $arguments): array
    {
        if ($this->hasArgument('--update-shards', $arguments)) {
            return $this->handleUpdateShards($arguments);
        }

        if (Parallel::isWorker() && Parallel::getGlobal('UPDATE_SHARDS') === true) {
            self::$updateShards = true;

            Event\Facade::instance()->registerSubscriber(new EnsureShardTimingStarted);
            Event\Facade::instance()->registerSubscriber(new EnsureShardTimingFinished);

            return $arguments;
        }

        if (! $this->hasArgument('--shard', $arguments)) {
            return $arguments;
        }

        // @phpstan-ignore-next-line
        $input = new ArgvInput($arguments);

        ['index' => $index, 'total' => $total] = self::getShard($input);

        $arguments = $this->popArgument("--shard=$index/$total", $this->popArgument('--shard', $this->popArgument(
            "$index/$total",
            $arguments,
        )));

        /** @phpstan-ignore-next-line */
        $tests = $this->allTests($arguments);

        $timings = $this->loadShardsFile();
        if ($timings !== null) {
            $knownTests = array_values(array_filter($tests, fn (string $test): bool => isset($timings[$test])));
            $newTests = array_values(array_diff($tests, $knownTests));

            $partitions = $this->partitionByTime($knownTests, $timings, $total);

            foreach ($newTests as $i => $test) {
                $partitions[$i % $total][] = $test;
            }

            $testsToRun = $partitions[$index - 1] ?? [];
            self::$timeBalanced = true;
            self::$shardsOutdated = $newTests !== [];
        } else {
            $testsToRun = (array_chunk($tests, max(1, (int) ceil(count($tests) / $total))))[$index - 1] ?? [];
        }

        self::$shard = [
            'index' => $index,
            'total' => $total,
            'testsRan' => count($testsToRun),
            'testsCount' => count($tests),
        ];

        if ($testsToRun === []) {
            return $arguments;
        }

        return [...$arguments, '--filter', $this->buildFilterArgument($testsToRun)];
    }

    /**
     * Handles the --update-shards argument.
     *
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function handleUpdateShards(array $arguments): array
    {
        if ($this->hasArgument('--shard', $arguments)) {
            throw new InvalidOption('The [--update-shards] option cannot be combined with [--shard].');
        }

        $arguments = $this->popArgument('--update-shards', $arguments);

        self::$updateShards = true;

        /** @phpstan-ignore-next-line */
        self::$knownTests = $this->allTests($arguments);

        if ($this->hasArgument('--parallel', $arguments) || $this->hasArgument('-p', $arguments)) {
            Parallel::setGlobal('UPDATE_SHARDS', true);
            Parallel::setGlobal('SHARD_RUN_ID', uniqid('pest-shard-', true));
        } else {
            Event\Facade::instance()->registerSubscriber(new EnsureShardTimingStarted);
            Event\Facade::instance()->registerSubscriber(new EnsureShardTimingFinished);
        }

        return $arguments;
    }

    /**
     * Returns all tests that the test suite would run.
     *
     * @param  list<string>  $arguments
     * @return list<string>
     */
    private function allTests(array $arguments): array
    {
        $output = (new Process([
            'php',
            ...$this->removeParallelArguments($arguments),
            '--list-tests',
        ]))->setTimeout(120)->mustRun()->getOutput();

        preg_match_all('/ - (?:P\\\\)?(Tests\\\\[^:]+)::/', $output, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function removeParallelArguments(array $arguments): array
    {
        return array_filter($arguments, fn (string $argument): bool => ! in_array($argument, ['--parallel', '-p'], strict: true));
    }

    /**
     * Builds the filter argument for the given tests to run.
     */
    private function buildFilterArgument(mixed $testsToRun): string
    {
        return addslashes(implode('|', $testsToRun));
    }

    /**
     * Adds output after the Test Suite execution.
     */
    public function addOutput(int $exitCode): int
    {
        self::$passed = $exitCode === 0;

        if (self::$updateShards && self::$passed && ! Parallel::isWorker()) {
            self::$collectedTimings = $this->collectTimings();

            $count = self::$knownTests !== null
                ? count(array_intersect_key(self::$collectedTimings, array_flip(self::$knownTests)))
                : count(self::$collectedTimings);

            $this->output->writeln(sprintf(
                '  <fg=gray>Shards:</>   <fg=default>shards.json updated with timings for %d test class%s.</>',
                $count,
                $count === 1 ? '' : 'es',
            ));
        }

        if (self::$shard === null) {
            return $exitCode;
        }

        [
            'index' => $index,
            'total' => $total,
            'testsRan' => $testsRan,
            'testsCount' => $testsCount,
        ] = self::$shard;

        $this->output->writeln(sprintf(
            '  <fg=gray>Shard:</>    <fg=default>%d of %d</> — %d file%s ran, out of %d%s.',
            $index,
            $total,
            $testsRan,
            $testsRan === 1 ? '' : 's',
            $testsCount,
            self::$timeBalanced ? ' <fg=gray>(time-balanced)</>' : '',
        ));

        if (self::$shardsOutdated) {
            $this->output->writeln('  <fg=yellow;options=bold>WARN</>  <fg=default>The [tests/.pest/shards.json] file is out of date. Run [--update-shards] to update it.</>');
        }

        return $exitCode;
    }

    /**
     * Terminates the plugin.
     */
    public function terminate(): void
    {
        if (! self::$updateShards) {
            return;
        }

        if (Parallel::isWorker()) {
            $this->writeWorkerTimings();

            return;
        }

        if (! self::$passed) {
            return;
        }

        $timings = self::$collectedTimings ?? $this->collectTimings();

        if ($timings === []) {
            return;
        }

        $this->writeTimings($timings);
    }

    /**
     * Collects timings from subscribers or worker temp files.
     *
     * @return array<string, float>
     */
    private function collectTimings(): array
    {
        $runId = Parallel::getGlobal('SHARD_RUN_ID');

        if (is_string($runId)) {
            return $this->readWorkerTimings($runId);
        }

        return EnsureShardTimingsAreCollected::timings();
    }

    /**
     * Writes the current worker's timing data to a temp file.
     */
    private function writeWorkerTimings(): void
    {
        $timings = EnsureShardTimingsAreCollected::timings();

        if ($timings === []) {
            return;
        }

        $runId = Parallel::getGlobal('SHARD_RUN_ID');

        if (! is_string($runId)) {
            return;
        }

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'__pest_sharding_'.$runId.'-'.getmypid().'.json';

        file_put_contents($path, json_encode($timings, JSON_THROW_ON_ERROR));
    }

    /**
     * Reads and merges timing data from all worker temp files.
     *
     * @return array<string, float>
     */
    private function readWorkerTimings(string $runId): array
    {
        $pattern = sys_get_temp_dir().DIRECTORY_SEPARATOR.'__pest_sharding_'.$runId.'-*.json';
        $files = glob($pattern);

        if ($files === false || $files === []) {
            return [];
        }

        $merged = [];

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            if ($contents === false) {
                continue;
            }

            $timings = json_decode($contents, true);

            if (is_array($timings)) {
                $merged = array_merge($merged, $timings);
            }

            unlink($file);
        }

        return $merged;
    }

    /**
     * Returns the path to shards.json.
     */
    private function shardsPath(): string
    {
        $testSuite = TestSuite::getInstance();

        return implode(DIRECTORY_SEPARATOR, [$testSuite->rootPath, $testSuite->testPath, '.pest', 'shards.json']);
    }

    /**
     * Loads the timings from shards.json.
     *
     * @return array<string, float>|null
     */
    private function loadShardsFile(): ?array
    {
        $path = $this->shardsPath();

        if (! file_exists($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new InvalidOption('The [tests/.pest/shards.json] file could not be read. Delete it or run [--update-shards] to regenerate.');
        }

        $data = json_decode($contents, true);

        if (! is_array($data) || ! isset($data['timings']) || ! is_array($data['timings'])) {
            throw new InvalidOption('The [tests/.pest/shards.json] file is corrupted. Delete it or run [--update-shards] to regenerate.');
        }

        return $data['timings'];
    }

    /**
     * Partitions tests across shards using the LPT (Longest Processing Time) algorithm.
     *
     * @param  list<string>  $tests
     * @param  array<string, float>  $timings
     * @return list<list<string>>
     */
    private function partitionByTime(array $tests, array $timings, int $total): array
    {
        $knownTimings = array_filter(
            array_map(fn (string $test): ?float => $timings[$test] ?? null, $tests),
            fn (?float $t): bool => $t !== null,
        );

        $median = $knownTimings !== [] ? $this->median(array_values($knownTimings)) : 1.0;

        $testsWithTimings = array_map(
            fn (string $test): array => ['test' => $test, 'time' => $timings[$test] ?? $median],
            $tests,
        );

        usort($testsWithTimings, fn (array $a, array $b): int => $b['time'] <=> $a['time']);

        /** @var list<list<string>> */
        $bins = array_fill(0, $total, []);
        /** @var non-empty-list<float> */
        $binTimes = array_fill(0, $total, 0.0);

        foreach ($testsWithTimings as $item) {
            $minIndex = array_search(min($binTimes), $binTimes, strict: true);
            assert(is_int($minIndex));

            $bins[$minIndex][] = $item['test'];
            $binTimes[$minIndex] += $item['time'];
        }

        return $bins;
    }

    /**
     * Calculates the median of an array of floats.
     *
     * @param  list<float>  $values
     */
    private function median(array $values): float
    {
        sort($values);

        $count = count($values);
        $middle = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }

    /**
     * Writes the timings to shards.json.
     *
     * @param  array<string, float>  $timings
     */
    private function writeTimings(array $timings): void
    {
        $path = $this->shardsPath();

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (self::$knownTests !== null) {
            $knownSet = array_flip(self::$knownTests);
            $timings = array_intersect_key($timings, $knownSet);
        }

        ksort($timings);

        $canonical = self::$knownTests ?? array_keys($timings);
        sort($canonical);

        file_put_contents($path, json_encode([
            'timings' => $timings,
            'checksum' => md5(implode("\n", $canonical)),
            'updated_at' => date('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    /**
     * Returns the shard information.
     *
     * @return array{index: int, total: int}
     */
    public static function getShard(InputInterface $input): array
    {
        if ($input->hasParameterOption('--'.self::SHARD_OPTION)) {
            $shard = $input->getParameterOption('--'.self::SHARD_OPTION);
        } else {
            $shard = null;
        }

        if (! is_string($shard) || ! preg_match('/^\d+\/\d+$/', $shard)) {
            throw new InvalidOption('The [--shard] option must be in the format "index/total".');
        }

        [$index, $total] = explode('/', $shard);

        if (! is_numeric($index) || ! is_numeric($total)) {
            throw new InvalidOption('The [--shard] option must be in the format "index/total".');
        }

        if ($index <= 0 || $total <= 0 || $index > $total) {
            throw new InvalidOption('The [--shard] option index must be a non-negative integer less than the total number of shards.');
        }

        $index = (int) $index;
        $total = (int) $total;

        return [
            'index' => $index,
            'total' => $total,
        ];
    }
}
