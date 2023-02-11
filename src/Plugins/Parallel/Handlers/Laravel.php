<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Handlers;

use Composer\InstalledVersions;
use Illuminate\Testing\ParallelRunner;
use ParaTest\Options;
use ParaTest\RunnerInterface;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Parallel\Paratest\WrapperRunner;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Laravel implements HandlesArguments
{
    use HandleArguments;

    public function handleArguments(array $arguments): array
    {
        if (! self::isALaravelApplication()) {
            return $arguments;
        }

        $this->setLaravelParallelRunner();

        $arguments = $this->setEnvironmentVariables($arguments);

        return $this->useLaravelRunner($arguments);
    }

    private function setLaravelParallelRunner(): void
    {
        ParallelRunner::resolveRunnerUsing( // @phpstan-ignore-line
            fn (Options $options, OutputInterface $output): RunnerInterface => new WrapperRunner($options, $output)
        );
    }

    private static function isALaravelApplication(): bool
    {
        if (! InstalledVersions::isInstalled('laravel/framework', false)) {
            return false;
        }

        return ! class_exists(\Orchestra\Testbench\TestCase::class);
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function setEnvironmentVariables(array $arguments): array
    {
        $_ENV['LARAVEL_PARALLEL_TESTING'] = 1;

        if ($this->hasArgument('--recreate-databases', $arguments)) {
            $_ENV['LARAVEL_PARALLEL_TESTING_RECREATE_DATABASES'] = 1;
        }

        if ($this->hasArgument('--drop-databases', $arguments)) {
            $_ENV['LARAVEL_PARALLEL_TESTING_DROP_DATABASES'] = 1;
        }

        $arguments = $this->popArgument('--recreate-databases', $arguments);

        return $this->popArgument('--drop-databases', $arguments);
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function useLaravelRunner(array $arguments): array
    {
        foreach ($arguments as $value) {
            if (str_starts_with($value, '--runner')) {
                $arguments = $this->popArgument($value, $arguments);
            }
        }

        return $this->pushArgument('--runner=\Illuminate\Testing\ParallelRunner', $arguments);
    }
}
