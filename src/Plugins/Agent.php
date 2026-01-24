<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Support\AgentOutput;
use Pest\Support\Container;
use Pest\TestSuite;
use PHPUnit\TestRunner\TestResult\Facade;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Agent implements AddsOutput, HandlesArguments
{
    use Concerns\HandleArguments;

    /**
     * The stored coverage object for agent output.
     */
    private static ?CodeCoverage $coverage = null;

    /**
     * The minimum coverage threshold.
     */
    private static ?float $coverageMin = null;

    /**
     * The stored memory usage for agent output.
     */
    private static ?float $memory = null;

    /**
     * The stored shard information for agent output.
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
     * Creates a new Plugin instance.
     */
    public function __construct(
        private readonly OutputInterface $output,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function handleArguments(array $arguments): array
    {
        if (! AgentOutput::isActive()) {
            return $arguments;
        }

        if (! in_array('--no-output', $arguments, true)) {
            return $this->pushArgument('--no-output', $arguments);
        }

        return $arguments;
    }

    /**
     * {@inheritDoc}
     */
    public function addOutput(int $exitCode): int
    {
        if (Parallel::isWorker()) {
            return $exitCode;
        }

        if (! AgentOutput::isActive()) {
            return $exitCode;
        }

        $testSuite = Container::getInstance()->get(TestSuite::class);
        assert($testSuite instanceof TestSuite);

        $result = Facade::result();

        $output = AgentOutput::buildTestResults($result, $testSuite->rootPath);

        if (self::$coverage instanceof \SebastianBergmann\CodeCoverage\CodeCoverage) {
            $output['coverage'] = AgentOutput::buildCoverage(self::$coverage, self::$coverageMin);
        }

        if (self::$memory !== null) {
            $output['memory'] = self::$memory;
        }

        if (self::$shard !== null) {
            $output['shard'] = self::$shard;
        }

        $this->output->writeln(AgentOutput::toJson($output));

        return $exitCode;
    }

    /**
     * Stores coverage data for agent output.
     */
    public static function setCoverage(CodeCoverage $coverage, ?float $min = null): void
    {
        self::$coverage = $coverage;
        self::$coverageMin = $min;
    }

    /**
     * Stores memory usage for agent output.
     */
    public static function setMemory(float $memory): void
    {
        self::$memory = $memory;
    }

    /**
     * Stores shard information for agent output.
     *
     * @param  array{
     *     index: int,
     *     total: int,
     *     testsRan: int,
     *     testsCount: int
     * }  $shard
     */
    public static function setShard(array $shard): void
    {
        self::$shard = $shard;
    }
}
