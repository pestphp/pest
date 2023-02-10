<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Handlers;

use Composer\InstalledVersions;
use Illuminate\Testing\ParallelRunner;
use ParaTest\Options;
use ParaTest\RunnerInterface;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Parallel\Contracts\HandlesSubprocessArguments;
use Pest\Plugins\Parallel\Paratest\WrapperRunner;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Laravel implements HandlesArguments, HandlesSubprocessArguments
{
    use HandleArguments;

    public function handleArguments(array $arguments): array
    {
        if (! self::isALaravelApplication()) {
            return $arguments;
        }

        $this->setLaravelParallelRunner();

        foreach ($arguments as $value) {
            if (str_starts_with((string) $value, '--runner')) {
                $arguments = $this->popArgument($value, $arguments);
            }
        }

        return $this->pushArgument('--runner=\Illuminate\Testing\ParallelRunner', $arguments);
    }

    private function setLaravelParallelRunner(): void
    {
        if (! method_exists(ParallelRunner::class, 'resolveRunnerUsing')) {
            exit('Using parallel with Pest requires Laravel v8.55.0 or higher.');
        }

        ParallelRunner::resolveRunnerUsing(fn (Options $options, OutputInterface $output): RunnerInterface => new WrapperRunner($options, $output));
    }

    private static function isALaravelApplication(): bool
    {
        return InstalledVersions::isInstalled('laravel/framework', false)
            && ! class_exists(\Orchestra\Testbench\TestCase::class);
    }

    public function handleSubprocessArguments(array $arguments): array
    {
        putenv('LARAVEL_PARALLEL_TESTING=1');

        return $arguments;
    }
}
