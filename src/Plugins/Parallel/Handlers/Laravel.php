<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Handlers;

use Closure;
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

    /**
     * {@inheritdoc}
     */
    public function handleArguments(array $arguments): array
    {
        return $this->whenUsingLaravel($arguments, function (array $arguments): array {
            $this->ensureRunnerIsResolvable();

            $arguments = $this->ensureEnvironmentVariables($arguments);

            return $this->ensureRunner($arguments);
        });
    }

    /**
     * Executes the given closure when running Laravel.
     *
     * @param  array<int, string>  $arguments
     * @param  CLosure(array<int, string>): array<int, string>  $closure
     * @return array<int, string>
     */
    private function whenUsingLaravel(array $arguments, Closure $closure): array
    {
        $isLaravelApplication = InstalledVersions::isInstalled('laravel/framework', false);
        $isLaravelPackage = class_exists(\Orchestra\Testbench\TestCase::class);

        if ($isLaravelApplication && ! $isLaravelPackage) {
            return $closure($arguments);
        }

        return $arguments;
    }

    /**
     * Ensures the runner is resolvable.
     */
    private function ensureRunnerIsResolvable(): void
    {
        ParallelRunner::resolveRunnerUsing( // @phpstan-ignore-line
            fn (Options $options, OutputInterface $output): RunnerInterface => new WrapperRunner($options, $output)
        );
    }

    /**
     * Ensures the environment variables are set.
     *
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function ensureEnvironmentVariables(array $arguments): array
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
     * Ensure the runner is set.
     *
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function ensureRunner(array $arguments): array
    {
        foreach ($arguments as $value) {
            if (str_starts_with($value, '--runner')) {
                $arguments = $this->popArgument($value, $arguments);
            }
        }

        return $this->pushArgument('--runner=\Illuminate\Testing\ParallelRunner', $arguments);
    }
}
