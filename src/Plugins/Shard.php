<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Exceptions\InvalidOption;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
final class Shard implements AddsOutput, HandlesArguments
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

        // @phpstan-ignore-next-line
        $input = new ArgvInput($arguments);

        ['index' => $index, 'total' => $total] = self::getShard($input);

        $arguments = $this->popArgument("--shard=$index/$total", $this->popArgument('--shard', $this->popArgument(
            "$index/$total",
            $arguments,
        )));

        /** @phpstan-ignore-next-line */
        $tests = $this->allTests($arguments);
        $testsToRun = (array_chunk($tests, max(1, (int) ceil(count($tests) / $total))))[$index - 1] ?? [];

        self::$shard = [
            'index' => $index,
            'total' => $total,
            'testsRan' => count($testsToRun),
            'testsCount' => count($tests),
        ];

        return [...$arguments, '--filter', $this->buildFilterArgument($testsToRun)];
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
            '  <fg=gray>Shard:</>    <fg=default>%d of %d</> — %d file%s ran, out of %d.',
            $index,
            $total,
            $testsRan,
            $testsRan === 1 ? '' : 's',
            $testsCount,
        ));

        return $exitCode;
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
