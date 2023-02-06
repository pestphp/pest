<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Handlers;

use Pest\Plugins\Concerns\HandleArguments;

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

        foreach ($args as $value) {
            if (str_starts_with($value, '--runner')) {
                $args = $this->popArgument($value, $args);
            }
        }

        return $this->pushArgument('--runner=\Illuminate\Testing\ParallelRunner', $args);
    }

    private static function isALaravelApplication(): bool
    {
        return class_exists(\Illuminate\Foundation\Application::class)
            && class_exists(\Illuminate\Testing\ParallelRunner::class)
            && !class_exists(\Orchestra\Testbench\TestCase::class);
    }
}
