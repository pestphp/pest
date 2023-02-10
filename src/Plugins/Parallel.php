<?php

declare(strict_types=1);

namespace Pest\Plugins;

use ParaTest\ParaTestCommand;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Actions\CallsAddsOutput;
use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Parallel\Contracts\HandlesSubprocessArguments;
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
        return (int) Arr::get($_SERVER, 'PARATEST') === 1;
    }

    public function handleArguments(array $arguments): array
    {
        if ($this->argumentsContainParallelFlags($arguments)) {
            exit($this->runTestSuiteInParallel($arguments));
        }

        if (self::isInParallelProcess()) {
            return $this->runSubprocessHandlers($arguments);
        }

        return $arguments;
    }

    private function argumentsContainParallelFlags(array $arguments): bool
    {
        if ($this->hasArgument('--parallel', $arguments)) {
            return true;
        }

        return $this->hasArgument('-p', $arguments);
    }

    private function runTestSuiteInParallel(array $arguments): int
    {
        if (! class_exists(ParaTestCommand::class)) {
            $this->askUserToInstallParatest();

            return Command::FAILURE;
        }

        $handlers = array_filter(
            array_map(fn ($handler) => Container::getInstance()->get($handler), self::HANDLERS),
            fn ($handler) => $handler instanceof HandlesArguments,
        );

        $filteredArguments = array_reduce(
            $handlers,
            fn ($arguments, HandlesArguments $handler) => $handler->handleArguments($arguments),
            $arguments
        );

        $exitCode = $this->paratestCommand()->run(new ArgvInput($filteredArguments), new CleanConsoleOutput());

        return (new CallsAddsOutput())($exitCode);
    }

    private function runSubprocessHandlers(array $arguments): array
    {
        $handlers = array_filter(
            array_map(fn ($handler) => Container::getInstance()->get($handler), self::HANDLERS),
            fn ($handler) => $handler instanceof HandlesSubprocessArguments,
        );

        return array_reduce(
            $handlers,
            fn ($arguments, HandlesSubprocessArguments $handler) => $handler->handleSubprocessArguments($arguments),
            $arguments
        );
    }

    private function askUserToInstallParatest(): void
    {
        Container::getInstance()->get(OutputInterface::class)->writeln([
            '<fg=red>Pest Parallel requires ParaTest to run.</>',
            'Please run <fg=yellow>composer require --dev brianium/paratest</>.',
        ]);
    }

    private function paratestCommand(): Application
    {
        $command = ParaTestCommand::applicationFactory(TestSuite::getInstance()->rootPath);
        $command->setAutoExit(false);
        $command->setName('Pest');
        $command->setVersion(version());

        return $command;
    }
}
