<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Handlers;

use Illuminate\Testing\ParallelRunner;
use ParaTest\Options;
use ParaTest\RunnerInterface;
use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Parallel\Paratest\WrapperRunner;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Laravel
{
    use HandleArguments;

    public function handle(array $args): array
    {
        if (! self::isALaravelApplication()) {
            return $args;
        }

        $this->setLaravelParallelRunner();

        foreach ($args as $value) {
            if (str_starts_with((string) $value, '--runner')) {
                $args = $this->popArgument($value, $args);
            }
        }

        return $this->pushArgument('--runner=\Illuminate\Testing\ParallelRunner', $args);
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
        return class_exists(\Illuminate\Foundation\Application::class)
            && class_exists(\Illuminate\Testing\ParallelRunner::class)
            && ! class_exists(\Orchestra\Testbench\TestCase::class);
    }
}
