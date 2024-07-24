<?php

declare(strict_types=1);

namespace Pest\Plugins;

use ParaTest\ParaTestCommand;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Actions\CallsAddsOutput;
use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Parallel\Contracts\HandlersWorkerArguments;
use Pest\Plugins\Parallel\Paratest\CleanConsoleOutput;
use Pest\Support\Arr;
use Pest\Support\Container;
use Pest\TestSuite;
use Stringable;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;

use function Pest\version;

final class Parallel implements HandlesArguments
{
    use HandleArguments;

    private const GLOBAL_PREFIX = 'PEST_PARALLEL_GLOBAL_';

    private const HANDLERS = [
        Parallel\Handlers\Parallel::class,
        Parallel\Handlers\Pest::class,
        Parallel\Handlers\Laravel::class,
    ];

    /**
     * @var string[]
     */
    private const UNSUPPORTED_ARGUMENTS = ['--todo', '--todos', '--retry', '--notes', '--issue', '--pr', '--pull-request'];

    /**
     * Whether the given command line arguments indicate that the test suite should be run in parallel.
     */
    public static function isEnabled(): bool
    {
        $argv = new ArgvInput;

        if ($argv->hasParameterOption('--parallel')) {
            return true;
        }

        return $argv->hasParameterOption('-p');
    }

    /**
     * If this code is running in a worker process rather than the main process.
     */
    public static function isWorker(): bool
    {
        $argvValue = Arr::get($_SERVER, 'PARATEST');

        assert(is_string($argvValue) || is_int($argvValue) || is_null($argvValue));

        return ((int) $argvValue) === 1;
    }

    /**
     * Sets a global value that can be accessed by the parent process and all workers.
     */
    public static function setGlobal(string $key, string|int|bool|Stringable $value): void
    {
        $data = ['value' => $value instanceof Stringable ? $value->__toString() : $value];

        $_ENV[self::GLOBAL_PREFIX.$key] = json_encode($data, JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the given global value if one has been set.
     */
    public static function getGlobal(string $key): string|int|bool|null
    {
        $placesToCheck = [$_SERVER, $_ENV];

        foreach ($placesToCheck as $location) {
            if (array_key_exists(self::GLOBAL_PREFIX.$key, $location)) {
                // @phpstan-ignore-next-line
                return json_decode((string) $location[self::GLOBAL_PREFIX.$key], true, 512, JSON_THROW_ON_ERROR)['value'] ?? null;
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function handleArguments(array $arguments): array
    {
        if ($this->hasArgumentsThatWouldBeFasterWithoutParallel()) {
            return $this->runTestSuiteInSeries($arguments);
        }

        if (self::isEnabled()) {
            exit($this->runTestSuiteInParallel($arguments));
        }

        if (self::isWorker()) {
            return $this->runWorkerHandlers($arguments);
        }

        return $arguments;
    }

    /**
     * Runs the test suite in parallel. This method will exit the process upon completion.
     *
     * @param  array<int, string>  $arguments
     */
    private function runTestSuiteInParallel(array $arguments): int
    {
        $handlers = array_filter(
            array_map(fn (string $handler): object|string => Container::getInstance()->get($handler), self::HANDLERS),
            fn (object|string $handler): bool => $handler instanceof HandlesArguments,
        );

        $filteredArguments = array_reduce(
            $handlers,
            fn (array $arguments, HandlesArguments $handler): array => $handler->handleArguments($arguments),
            $arguments
        );

        $exitCode = $this->paratestCommand()->run(new ArgvInput($filteredArguments), new CleanConsoleOutput);

        return CallsAddsOutput::execute($exitCode);
    }

    /**
     * Runs any handlers that have been registered to handle worker arguments, and returns the modified arguments.
     *
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function runWorkerHandlers(array $arguments): array
    {
        $handlers = array_filter(
            array_map(fn (string $handler): object|string => Container::getInstance()->get($handler), self::HANDLERS),
            fn (object|string $handler): bool => $handler instanceof HandlersWorkerArguments,
        );

        return array_reduce(
            $handlers,
            fn (array $arguments, HandlersWorkerArguments $handler): array => $handler->handleWorkerArguments($arguments),
            $arguments
        );
    }

    /**
     * Builds an instance of the Paratest command.
     */
    private function paratestCommand(): Application
    {
        /** @var non-empty-string $rootPath */
        $rootPath = TestSuite::getInstance()->rootPath;

        $command = ParaTestCommand::applicationFactory($rootPath);
        $command->setAutoExit(false);
        $command->setName('Pest');
        $command->setVersion(version());

        return $command;
    }

    /**
     * Whether the command line arguments contain any arguments that are
     * not supported or are suboptimal when running in parallel.
     */
    private function hasArgumentsThatWouldBeFasterWithoutParallel(): bool
    {
        $arguments = new ArgvInput;

        foreach (self::UNSUPPORTED_ARGUMENTS as $unsupportedArgument) {
            if ($arguments->hasParameterOption($unsupportedArgument)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes any parallel arguments.
     *
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function runTestSuiteInSeries(array $arguments): array
    {
        $arguments = $this->popArgument('--parallel', $arguments);

        return $this->popArgument('-p', $arguments);
    }
}
