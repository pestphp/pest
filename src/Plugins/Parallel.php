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
use function Pest\version;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;

final class Parallel implements HandlesArguments
{
    use HandleArguments;

    private const HANDLERS = [
        Parallel\Handlers\Parallel::class,
        Parallel\Handlers\Pest::class,
        Parallel\Handlers\Laravel::class,
    ];

    /**
     * @var string[]
     */
    private const UNSUPPORTED_ARGUMENTS = ['--todos', '--retry'];

    /**
     * Whether the given command line arguments indicate that the test suite should be run in parallel.
     */
    public static function isEnabled(): bool
    {
        $argv = new ArgvInput();
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
        if (! class_exists(ParaTestCommand::class)) {
            $this->askUserToInstallParatest();

            return Command::FAILURE;
        }

        $handlers = array_filter(
            array_map(fn ($handler): object|string => Container::getInstance()->get($handler), self::HANDLERS),
            fn ($handler): bool => $handler instanceof HandlesArguments,
        );

        $filteredArguments = array_reduce(
            $handlers,
            fn ($arguments, HandlesArguments $handler): array => $handler->handleArguments($arguments),
            $arguments
        );

        $exitCode = $this->paratestCommand()->run(new ArgvInput($filteredArguments), new CleanConsoleOutput());

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
            array_map(fn ($handler): object|string => Container::getInstance()->get($handler), self::HANDLERS),
            fn ($handler): bool => $handler instanceof HandlersWorkerArguments,
        );

        return array_reduce(
            $handlers,
            fn ($arguments, HandlersWorkerArguments $handler): array => $handler->handleWorkerArguments($arguments),
            $arguments
        );
    }

    /**
     * Outputs a message to the user asking them to install ParaTest as a dev dependency.
     */
    private function askUserToInstallParatest(): void
    {
        /** @var OutputInterface $output */
        $output = Container::getInstance()->get(OutputInterface::class);

        $output->writeln([
            '<fg=red>Pest Parallel requires ParaTest to run.</>',
            'Please run <fg=yellow>composer require --dev brianium/paratest</>.',
        ]);
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
        $arguments = new ArgvInput();

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
