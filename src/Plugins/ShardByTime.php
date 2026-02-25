<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use PHPUnit\TextUI\CliArguments\Builder as CliConfigurationBuilder;
use PHPUnit\TextUI\CliArguments\XmlConfigurationFileFinder;
use PHPUnit\TextUI\XmlConfiguration\DefaultConfiguration;
use PHPUnit\TextUI\XmlConfiguration\Loader;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
final class ShardByTime implements AddsOutput, HandlesArguments
{
    use Concerns\HandleArguments;

    private const string TEMPORARY_FOLDER = __DIR__
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'.temp';

    private const float DEFAULT_TIME = 1.0;

    /**
     * @var array{
     *     index: int,
     *     total: int,
     *     testsRan: int,
     *     testsCount: int
     * }|null
     */
    private static ?array $shard = null;

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
        if (! $this->hasArgument('--shard', $arguments)) {
            return $arguments;
        }

        $timingFile = $_SERVER['PEST_SHARD_TIMING'] ?? null;

        if ($timingFile === null) {
            return $arguments;
        }

        // @phpstan-ignore-next-line
        $input = new ArgvInput($arguments);

        ['index' => $index, 'total' => $total] = Shard::getShard($input);

        $arguments = $this->popArgument("--shard=$index/$total", $this->popArgument('--shard', $this->popArgument(
            "$index/$total",
            $arguments,
        )));

        /** @phpstan-ignore-next-line */
        $tests = $this->allTests($arguments);

        $classTimes = $this->loadClassTimes($timingFile, array_values($arguments));

        $testsToRun = $classTimes !== null
            ? $this->shardByTime($tests, $total, $index, $classTimes)
            : (array_chunk($tests, max(1, (int) ceil(count($tests) / $total))))[$index - 1] ?? [];

        self::$shard = [
            'index' => $index,
            'total' => $total,
            'testsRan' => count($testsToRun),
            'testsCount' => count($tests),
        ];

        return [...$arguments, '--filter', $this->buildFilterArgument($testsToRun)];
    }

    /**
     * Shards tests by execution time using the LPT bin-packing algorithm.
     *
     * @param  list<string>  $tests
     * @param  array<string, float>  $classTimes
     * @return list<string>
     */
    private function shardByTime(array $tests, int $total, int $index, array $classTimes): array
    {
        usort($tests, fn (string $a, string $b): int => ($classTimes[$b] ?? self::DEFAULT_TIME) <=> ($classTimes[$a] ?? self::DEFAULT_TIME));

        /** @var list<list<string>> $shards */
        $shards = array_fill(0, $total, []);
        /** @var list<float> $shardTimes */
        $shardTimes = array_fill(0, $total, 0.0);

        foreach ($tests as $test) {
            $minIndex = 0;
            $minTime = $shardTimes[0];

            for ($i = 1; $i < $total; $i++) {
                if ($shardTimes[$i] < $minTime) {
                    $minTime = $shardTimes[$i];
                    $minIndex = $i;
                }
            }

            $shards[$minIndex][] = $test;
            $shardTimes[$minIndex] += $classTimes[$test] ?? self::DEFAULT_TIME;
        }

        return $shards[$index - 1] ?? [];
    }

    /**
     * Loads per-class execution times, preferring JUnit XML, falling back to result cache.
     *
     * @param  list<string>  $arguments
     * @return array<string, float>|null
     */
    private function loadClassTimes(string $timingFile, array $arguments): ?array
    {
        return $this->loadClassTimesFromJunitXml($timingFile)
            ?? $this->loadClassTimesFromResultCache($arguments);
    }

    /**
     * Loads per-class execution times from a JUnit XML report.
     *
     * @return array<string, float>|null
     */
    private function loadClassTimesFromJunitXml(string $file): ?array
    {
        if (! file_exists($file)) {
            return null;
        }

        $xml = simplexml_load_file($file);

        if ($xml === false) {
            return null;
        }

        /** @var array<string, float> $classTimes */
        $classTimes = [];

        foreach ($xml->xpath('//testsuite[@class]') as $suite) {
            $class = (string) $suite['class'];
            $time = (float) $suite['time'];

            $classTimes[$class] = ($classTimes[$class] ?? 0.0) + $time;
        }

        if ($classTimes === []) {
            foreach ($xml->xpath('//testcase[@class]') as $testcase) {
                $class = (string) $testcase['class'];
                $time = (float) $testcase['time'];

                $classTimes[$class] = ($classTimes[$class] ?? 0.0) + $time;
            }
        }

        return $classTimes !== [] ? $classTimes : null;
    }

    /**
     * Loads per-class execution times from the PHPUnit result cache.
     *
     * @param  list<string>  $arguments
     * @return array<string, float>|null
     */
    private function loadClassTimesFromResultCache(array $arguments): ?array
    {
        $cacheFile = $this->findCacheFile($arguments);

        if ($cacheFile === null || ! file_exists($cacheFile)) {
            return null;
        }

        $contents = file_get_contents($cacheFile);

        if ($contents === false) {
            return null;
        }

        $data = json_decode($contents, true);

        if (! is_array($data) || ! isset($data['times']) || ! is_array($data['times'])) { // @phpstan-ignore booleanNot.alwaysFalse
            return null;
        }

        /** @var array<string, float> $classTimes */
        $classTimes = [];

        /** @var array<string, float> $times */
        $times = $data['times'];

        foreach ($times as $method => $time) {
            $parts = explode('::', $method, 2);
            $class = preg_replace('/^P\\\\/', '', $parts[0]);

            $classTimes[$class] = ($classTimes[$class] ?? 0.0) + $time;
        }

        return $classTimes;
    }

    /**
     * Finds the result cache file path.
     *
     * @param  list<string>  $arguments
     */
    private function findCacheFile(array $arguments): ?string
    {
        $cacheDirectory = $this->getCacheDirectoryFromArguments($arguments);

        if ($cacheDirectory === null) {
            $cacheDirectory = $this->getCacheDirectoryFromXml();
        }

        if ($cacheDirectory === null) {
            $tempFolder = realpath(self::TEMPORARY_FOLDER);
            $cacheDirectory = $tempFolder !== false ? $tempFolder : null;
        }

        if ($cacheDirectory === null) {
            return null;
        }

        $testResults = $cacheDirectory.DIRECTORY_SEPARATOR.'test-results';

        if (file_exists($testResults)) {
            return $testResults;
        }

        return $cacheDirectory.DIRECTORY_SEPARATOR.'.phpunit.result.cache';
    }

    /**
     * @param  list<string>  $arguments
     */
    private function getCacheDirectoryFromArguments(array $arguments): ?string
    {
        foreach ($arguments as $i => $argument) {
            if ($argument === '--cache-directory' && isset($arguments[$i + 1])) {
                return $arguments[$i + 1];
            }

            if (str_starts_with($argument, '--cache-directory=')) {
                return substr($argument, strlen('--cache-directory='));
            }
        }

        return null;
    }

    private function getCacheDirectoryFromXml(): ?string
    {
        $cliConfiguration = (new CliConfigurationBuilder)->fromParameters([]);
        $configurationFile = (new XmlConfigurationFileFinder)->find($cliConfiguration);
        $xmlConfiguration = DefaultConfiguration::create();

        if (is_string($configurationFile)) {
            $xmlConfiguration = (new Loader)->load($configurationFile);
        }

        if ($xmlConfiguration->phpunit()->hasCacheDirectory()) {
            return $xmlConfiguration->phpunit()->cacheDirectory();
        }

        return null;
    }

    /**
     * @param  list<string>  $arguments
     * @return list<string>
     */
    private function allTests(array $arguments): array
    {
        $output = (new Process([
            'php',
            ...$this->removeParallelArguments($arguments),
            '--list-tests',
        ]))->mustRun()->getOutput();

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
     * @param  list<string>  $testsToRun
     */
    private function buildFilterArgument(array $testsToRun): string
    {
        return addslashes(implode('|', $testsToRun));
    }

    /**
     * Adds output after the Test Suite execution.
     */
    public function addOutput(int $exitCode): int
    {
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
            '  <fg=gray>Shard:</>    <fg=default>%d of %d</> — %d file%s ran, out of %d (time-balanced).',
            $index,
            $total,
            $testsRan,
            $testsRan === 1 ? '' : 's',
            $testsCount,
        ));

        return $exitCode;
    }
}
