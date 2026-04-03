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
        $arguments = array_flip($arguments);

        unset($arguments[$argument]);

        return array_values(array_flip($arguments));
    }

    /**
     * Pops the given argument and its value from the arguments, returning the value.
     *
     * @param  array<int, string>  $arguments
     */
    public function popArgumentValue(string $argument, array &$arguments): ?string
    {
        foreach ($arguments as $key => $value) {
            if (str_contains($value, "$argument=")) {
                unset($arguments[$key]);
                $arguments = array_values($arguments);

                return substr($value, strlen($argument) + 1);
            }

            if ($value === $argument && isset($arguments[$key + 1])) {
                $result = $arguments[$key + 1];
                unset($arguments[$key], $arguments[$key + 1]);
                $arguments = array_values($arguments);

                return $result;
            }
        }

        return null;
    }
}
