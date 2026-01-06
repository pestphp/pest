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
     * The maximum length allowed for the filter argument.
     * While ARG_MAX can be 2MB, individual arguments are often limited to 128KB (MAX_ARG_STRLEN).
     * Practical limits in CI environments (like Docker or pipeline runners) can be even lower.
     */
    private const int MAX_FILTER_LENGTH = 32768;

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

        $filter = $this->buildFilterArgument($testsToRun);

        $this->ensureFilterLengthIsSafe($filter);

        return [...$arguments, '--filter', $filter];
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
     *
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

        $filter = $buildRegex($tree);

        $this->ensureFilterLengthIsSafe($filter);

        return $filter;
    }

    /**
     * Ensures that the filter length is safe for the current environment.
     *
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
