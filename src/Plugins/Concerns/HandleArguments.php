<?php

declare(strict_types=1);

namespace Pest\Plugins\Concerns;

/**
 * @internal
 */
trait HandleArguments
{
    /**
     * Checks if the given argument exists on the arguments.
     *
     * @param  array<int, string>  $arguments
     */
    public function hasArgument(string $argument, array $arguments): bool
    {
        foreach ($arguments as $arg) {
            if ($arg === $argument) {
                return true;
            }

            if (str_starts_with((string) $arg, "$argument=")) { // @phpstan-ignore-line
                return true;
            }
        }

        return false;
    }

    /**
     * Adds the given argument and value to the list of arguments.
     *
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    public function pushArgument(string $argument, array $arguments): array
    {
        $arguments[] = $argument;

        return $arguments;
    }

    /**
     * Pops the given argument from the arguments.
     *
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    public function popArgument(string $argument, array $arguments): array
    {
        $args = $arguments;
        $key = array_search($argument, $arguments, true);

        if ($key !== false) {
            array_splice($args, $key, 1);
        }

        return $args;

    }
}
