<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Handlers;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\App;
use Illuminate\Testing\ParallelRunner;
use NunoMaduro\Collision\Adapters\Laravel\Commands\TestCommand;
use ParaTest\Options;
use ParaTest\RunnerInterface;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Parallel\Paratest\WrapperRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpProcess;

/**
 * @internal
 */
final class Laravel implements HandlesArguments
{
    use HandleArguments;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly InputInterface $input,
    )
    {
    }

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
        if (! method_exists(ParallelRunner::class, 'resolveRunnerUsing')) {
            $this->output->writeln('  <fg=red>Using parallel with Pest requires Laravel v8.55.0 or higher.</>');
            exit(Command::FAILURE);
        }

        ParallelRunner::resolveRunnerUsing(fn (Options $options, OutputInterface $output): RunnerInterface => new WrapperRunner($options, $output));
    }

    private static function isALaravelApplication(): bool
    {
        return InstalledVersions::isInstalled('laravel/framework', false)
            && ! class_exists(\Orchestra\Testbench\TestCase::class);
    }

    /**
     * @param array<int, string> $arguments
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
     * @param array<int, string> $arguments
     * @return array<int, string>
     */
    private function useLaravelRunner(array $arguments): array
    {
        foreach ($arguments as $value) {
            if (str_starts_with((string)$value, '--runner')) {
                $arguments = $this->popArgument($value, $arguments);
            }
        }

        return $this->pushArgument('--runner=\Illuminate\Testing\ParallelRunner', $arguments);
    }
}
