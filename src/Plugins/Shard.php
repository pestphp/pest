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

    private const int MAX_FILTER_LENGTH = 32768;

    /**
     * @var array{
     *     index: int,
     *     total: int,
     *     testsRan: int,
     *     testsCount: int
     * }|null
     */
    private static ?array $shard = null;

    private static bool $updateShards = false;

    private static ?string $timingsFilename = null;

    /**
     * @var array<string, float|array{time: float, tests: list<string>}>|null
     */
    private static ?array $externalTimings = null;

    /**
     * @var list<string>
     */
    private static array $selectedUnits = [];

    /**
     * @var array<string, scalar>
     */
    private static array $metadata = [];

    private static bool $timeBalanced = false;

    private static bool $shardsOutdated = false;

    private static bool $passed = false;

    /**
     * @var array<string, float|array{time: float, tests: list<string>}>|null
     */
    private static ?array $collectedTimings = null;

    /**
     * @var list<string>|null
     */
    private static ?array $knownTests = null;

    public function __construct(
        private readonly OutputInterface $output,
    ) {
        //
    }

    public static function useTimingsFile(string $filename): void
    {
        self::$timingsFilename = $filename;
    }

    /**
     * @param  array<string, float|array{time: float, tests: list<string>}>  $units
     * @param  array<string, scalar>  $metadata  Stored alongside the units, and read back
     *                                           by {@see self::metadata()} on sharded runs.
     */
    public static function useTimings(array $units, array $metadata = []): void
    {
        self::$externalTimings = $units;

        if ($metadata !== []) {
            self::$metadata = $metadata;
        }
    }

    /**
     * @return array<string, scalar>
     */
    public static function metadata(): array
    {
        return self::$metadata;
    }

    /**
     * @return list<string>
     */
    public static function selectedUnits(): array
    {
        return self::$selectedUnits;
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

        $units = $this->loadShardsFile();
        if ($units !== null) {
            $newTests = array_values(array_diff($tests, $this->testsOf($units)));

            $partitions = $this->partitionByTime($units, $total);

            $median = $this->medianTime($units);

            foreach ($newTests as $i => $test) {
                $partitions[$i % $total][$test] = ['time' => $median, 'tests' => [$test]];
            }

            $selected = $partitions[$index - 1] ?? [];

            self::$selectedUnits = array_keys($selected);
            self::$timeBalanced = true;
            self::$shardsOutdated = $newTests !== [];

            $testsToRun = array_values(array_intersect($tests, $this->testsOf($selected)));
        } else {
            $isInCurrentShard = fn (int $key): bool => $key % $total === ($index - 1);
            $testsToRun = array_values(array_filter($tests, $isInCurrentShard, ARRAY_FILTER_USE_KEY));
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

        $filter = $this->buildFilterArgument($testsToRun);

        $this->ensureFilterLengthIsSafe($filter);

        return [...$arguments, '--filter', $filter];
    }

    /**
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
     * @param  list<string>  $arguments
     * @return list<string>
     */
    private function allTests(array $arguments): array
    {
        $command = $this->buildListTestsCommand(
            $arguments,
            TestSuite::getInstance()->testPath,
        );

        $output = new Process($command)->setTimeout(120)->mustRun()->getOutput();

        return $this->parseListTestsOutput($output);
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function removeParallelArguments(array $arguments): array
    {
        return array_values(array_filter(
            $arguments,
            fn (string $argument): bool => ! in_array($argument, ['--parallel', '-p'], strict: true)
                && ! str_starts_with($argument, '--processes'),
        ));
    }

    /**
     * @param  list<string>  $arguments
     * @return list<string>
     */
    private function buildListTestsCommand(array $arguments, string $testPath): array
    {
        $filtered = array_filter(
            $this->removeParallelArguments($arguments),
            fn (string $argument): bool => ! str_starts_with($argument, '--coverage'),
        );

        return ['php', ...array_values($filtered), '--test-directory='.$testPath, '--list-tests'];
    }

    /**
     * @return list<string>
     */
    private function parseListTestsOutput(string $output): array
    {
        preg_match_all('/ - (?:P\\\\)?([A-Za-z_]\w*(?:\\\\[A-Za-z_]\w*)*)::/', $output, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param  array<int, string>  $testsToRun
     */
    private function buildFilterArgument(array $testsToRun): string
    {
        if ($testsToRun === []) {
            return '';
        }

        /** @var array<string, mixed> $tree */
        $tree = [];
        foreach ($testsToRun as $class) {
            $parts = explode('\\', $class);
            $current = &$tree;
            foreach ($parts as $part) {
                if (! isset($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
        }

        $buildRegex = function (array $tree) use (&$buildRegex): string {
            $parts = [];
            foreach ($tree as $key => $sub) {
                $subRegex = $buildRegex($sub);
                if ($subRegex === '') {
                    $parts[] = preg_quote($key, '/');
                } else {
                    $parts[] = preg_quote($key, '/').'\\\\'.(count($sub) > 1 ? '('.$subRegex.')' : $subRegex);
                }
            }

            return implode('|', $parts);
        };

        return $buildRegex($tree);
    }

    /**
     * @throws InvalidOption
     */
    private function ensureFilterLengthIsSafe(string $filter): void
    {
        $maxLength = (int) (getenv('PEST_SHARD_MAX_FILTER_LENGTH') ?: self::MAX_FILTER_LENGTH);

        if (strlen($filter) > $maxLength) {
            throw new InvalidOption(sprintf(
                'The generated filter for this shard is too long (%d characters). '.
                'This can cause issues with some environments (limit is %d characters). '.
                'Please increase the number of shards (e.g., use 1/4 instead of 1/2) to reduce the filter length.',
                strlen($filter),
                $maxLength
            ));
        }
    }

    public function addOutput(int $exitCode): int
    {
        self::$passed = $exitCode === 0;

        if (self::$updateShards && (self::$passed || self::$externalTimings !== null) && ! Parallel::isWorker()) {
            self::$collectedTimings = $this->collectTimings();

            $count = count($this->unitsToWrite(self::$collectedTimings));

            $this->output->writeln(sprintf(
                '  <fg=gray>Shards:</>   <fg=default>%s updated with timings for %d test class%s.</>',
                $this->timingsFilename(),
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
            $this->output->writeln(sprintf(
                '  <fg=yellow;options=bold>WARN</>  <fg=default>The [%s] file is out of date. Run [--update-shards] to update it.</>',
                $this->relativeTimingsPath(),
            ));
        }

        return $exitCode;
    }

    public function terminate(): void
    {
        if (! self::$updateShards) {
            return;
        }

        if (Parallel::isWorker()) {
            $this->writeWorkerTimings();

            return;
        }

        if (! self::$passed && self::$externalTimings === null) {
            return;
        }

        $timings = self::$collectedTimings ?? $this->collectTimings();

        if ($timings === []) {
            return;
        }

        $this->writeTimings($timings);
    }

    /**
     * @return array<string, float|array{time: float, tests: list<string>}>
     */
    private function collectTimings(): array
    {
        if (self::$externalTimings !== null) {
            return self::$externalTimings;
        }

        $runId = Parallel::getGlobal('SHARD_RUN_ID');

        if (is_string($runId)) {
            return $this->readWorkerTimings($runId);
        }

        return EnsureShardTimingsAreCollected::timings();
    }

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

    private function timingsFilename(): string
    {
        return self::$timingsFilename ?? 'shards.json';
    }

    private function relativeTimingsPath(): string
    {
        return implode(DIRECTORY_SEPARATOR, [TestSuite::getInstance()->testPath, '.pest', $this->timingsFilename()]);
    }

    private function shardsPath(): string
    {
        return TestSuite::getInstance()->rootPath.DIRECTORY_SEPARATOR.$this->relativeTimingsPath();
    }

    /**
     * @param  array<string, mixed>  $units
     * @return array<string, array{time: float, tests: list<string>}>
     */
    private function normaliseUnits(array $units): array
    {
        $normalised = [];

        foreach ($units as $key => $unit) {
            if (is_array($unit) && isset($unit['time']) && isset($unit['tests']) && is_array($unit['tests'])) {
                $normalised[$key] = ['time' => (float) $unit['time'], 'tests' => array_values(array_map(strval(...), $unit['tests']))];

                continue;
            }

            if (is_float($unit) || is_int($unit)) {
                $normalised[$key] = ['time' => (float) $unit, 'tests' => [$key]];
            }
        }

        return $normalised;
    }

    /**
     * @return array<string, array{time: float, tests: list<string>}>|null
     */
    private function loadShardsFile(): ?array
    {
        $path = $this->shardsPath();

        if (! file_exists($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new InvalidOption(sprintf('The [%s] file could not be read. Delete it or run [--update-shards] to regenerate.', $this->relativeTimingsPath()));
        }

        $data = json_decode($contents, true);

        $units = null;

        if (is_array($data)) {
            $units = $data['units'] ?? $data['timings'] ?? null;
        }

        if (! is_array($units)) {
            throw new InvalidOption(sprintf('The [%s] file is corrupted. Delete it or run [--update-shards] to regenerate.', $this->relativeTimingsPath()));
        }

        if (is_array($data) && isset($data['metadata']) && is_array($data['metadata'])) {
            self::$metadata = array_filter($data['metadata'], is_scalar(...));
        }

        return $this->normaliseUnits($units);
    }

    /**
     * @param  array<string, array{time: float, tests: list<string>}>  $units
     * @return list<string>
     */
    private function testsOf(array $units): array
    {
        $tests = [];

        foreach ($units as $unit) {
            foreach ($unit['tests'] as $test) {
                $tests[$test] = true;
            }
        }

        return array_keys($tests);
    }

    /**
     * @param  array<string, array{time: float, tests: list<string>}>  $units
     */
    private function medianTime(array $units): float
    {
        $times = array_column($units, 'time');

        return $times === [] ? 1.0 : $this->median($times);
    }

    /**
     * @param  array<string, array{time: float, tests: list<string>}>  $units
     * @return list<array<string, array{time: float, tests: list<string>}>>
     */
    private function partitionByTime(array $units, int $total): array
    {
        uasort($units, fn (array $a, array $b): int => $b['time'] <=> $a['time']);

        /** @var list<array<string, array{time: float, tests: list<string>}>> */
        $bins = array_fill(0, $total, []);
        /** @var non-empty-list<float> */
        $binTimes = array_fill(0, $total, 0.0);

        foreach ($units as $key => $unit) {
            $minIndex = array_search(min($binTimes), $binTimes, strict: true);
            assert(is_int($minIndex));

            $bins[$minIndex][$key] = $unit;
            $binTimes[$minIndex] += $unit['time'];
        }

        return $bins;
    }

    /**
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
     * @param  array<string, float|array{time: float, tests: list<string>}>  $units
     * @return array<string, array{time: float, tests: list<string>}>
     */
    private function unitsToWrite(array $units): array
    {
        $units = $this->normaliseUnits($units);

        if (self::$knownTests === null) {
            return $units;
        }

        $known = self::$knownTests;

        $units = array_filter($units, fn (array $unit): bool => array_intersect($unit['tests'], $known) !== []);

        foreach (array_diff($known, $this->testsOf($units)) as $test) {
            $units[$test] = ['time' => 0.0, 'tests' => [$test]];
        }

        return $units;
    }

    /**
     * @param  array<string, float|array{time: float, tests: list<string>}>  $units
     */
    private function writeTimings(array $units): void
    {
        $path = $this->shardsPath();

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $bundled = self::$externalTimings !== null && array_filter($units, is_array(...)) !== [];

        $units = $this->unitsToWrite($units);

        ksort($units);

        $canonical = self::$knownTests ?? $this->testsOf($units);
        sort($canonical);

        $payload = $bundled
            ? ['units' => $units]
            : ['timings' => array_map(fn (array $unit): float => $unit['time'], $units)];

        if (self::$metadata !== []) {
            $payload['metadata'] = self::$metadata;
        }

        file_put_contents($path, json_encode([
            ...$payload,
            'checksum' => md5(implode("\n", $canonical)),
            'updated_at' => date('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    /**
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
