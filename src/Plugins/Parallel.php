<?php

namespace Pest\Plugins;

use ParaTest\ParaTestCommand;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Actions\CallsAddsOutput;
use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Parallel\Handlers\Laravel;
use Pest\Support\Arr;
use Pest\Support\Container;
use Pest\TestSuite;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;
use function Pest\version;

final class Parallel implements HandlesArguments
{
    use HandleArguments;

    private const HANDLERS = [
        Parallel\Handlers\Parallel::class,
        Laravel::class,
    ];

    public function handleArguments(array $arguments): array
    {
        if ($this->argumentsContainParallelFlags($arguments)) {
            exit($this->runTestSuiteInParallel($arguments));
        }

        $this->markTestSuiteAsParallelSubProcessIfRequired();

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

        $filteredArguments = array_reduce(
            self::HANDLERS,
            fn($arguments, $handler) => (new $handler())->handle($arguments),
            $arguments
        );

        $exitCode = $this->paratestCommand()->run(new ArgvInput($filteredArguments));

        return (new CallsAddsOutput())($exitCode);
    }

    private function markTestSuiteAsParallelSubProcessIfRequired(): void
    {
        if ((int) Arr::get($_SERVER, 'PARATEST') === 1) {
            $_SERVER['PEST_PARALLEL'] = 1;
        }
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
