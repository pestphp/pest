<?php

namespace Pest\Plugins;

use ParaTest\ParaTestCommand;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Actions\CallsAddsOutput;
use Pest\Plugins\Concerns\HandleArguments;
use Pest\Support\Arr;
use Pest\Support\Container;
use Pest\TestSuite;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;
use function Pest\version;

final class Parallel implements HandlesArguments
{
    use HandleArguments;

    private const HANDLERS = [
        \Pest\Plugins\Parallel\Handlers\Parallel::class,
        \Pest\Plugins\Parallel\Handlers\Laravel::class,
    ];

    public function handleArguments(array $arguments): array
    {
        if ($this->argumentsContainParallelFlags($arguments)) {
            $exitCode = $this->runTestSuiteInParallel($arguments);
            exit($exitCode);
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

            return 1;
        }

        $filteredArguments = array_reduce(
            self::HANDLERS,
            fn($arguments, $handler) => (new $handler())->handle($arguments),
            $arguments
        );

        $testSuite = TestSuite::getInstance();

        $command = ParaTestCommand::applicationFactory($testSuite->rootPath);
        $command->setAutoExit(false);
        $command->setName('Pest');
        $command->setVersion(version());
        $exitCode = $command->run(new ArgvInput($filteredArguments));

        $exitCode = (new CallsAddsOutput())($exitCode);
        exit($exitCode);
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
            '<fg=red>Parallel support requires ParaTest, which is not installed.</>',
            'Please run <fg=yellow>composer require --dev brianium/paratest</> to install ParaTest.',
        ]);
    }
}
