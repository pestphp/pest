<?php

declare(strict_types=1);

namespace Pest\Plugins;

use JsonException;
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

    public static function isInParallelProcess(): bool
    {
        $argvValue = Arr::get($_SERVER, 'PARATEST');

        assert(is_string($argvValue) || is_int($argvValue) || is_null($argvValue));

        return ((int) $argvValue) === 1;
    }

    public function handleArguments(array $arguments): array
    {
        if ($this->argumentsContainParallelFlags($arguments)) {
            exit($this->runTestSuiteInParallel($arguments));
        }

        if (self::isInParallelProcess()) {
            return $this->runWorkersHandlers($arguments);
        }

        return $arguments;
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function argumentsContainParallelFlags(array $arguments): bool
    {
        if ($this->hasArgument('--parallel', $arguments)) {
            return true;
        }

        return $this->hasArgument('-p', $arguments);
    }

    /**
     * @param  array<int, string>  $arguments
     *
     * @throws JsonException
     */
    private function runTestSuiteInParallel(array $arguments): int
    {
        if (! class_exists(ParaTestCommand::class)) {
            $this->askUserToInstallParatest();

            return Command::FAILURE;
        }

        $_ENV['PEST_PARALLEL_ARGV'] = json_encode($_SERVER['argv'], JSON_THROW_ON_ERROR);

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

        return (new CallsAddsOutput())($exitCode);
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function runWorkersHandlers(array $arguments): array
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

    private function askUserToInstallParatest(): void
    {
        /** @var OutputInterface $output */
        $output = Container::getInstance()->get(OutputInterface::class);

        $output->writeln([
            '<fg=red>Pest Parallel requires ParaTest to run.</>',
            'Please run <fg=yellow>composer require --dev brianium/paratest</>.',
        ]);
    }

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
}
