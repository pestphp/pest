<?php

declare(strict_types=1);

namespace Pest\Plugins\Concerns;

/**
 * @internal
 */
trait HandleArguments
{
    /**
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
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    public function pushArgument(string $argument, array $arguments): array
    {
        $arguments[] = $argument;

        return $arguments;
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    public function popArgument(string $argument, array $arguments): array
    {
        $key = array_search($argument, $arguments, true);

        while ($key !== false) {
            unset($arguments[$key]);
            $key = array_search($argument, $arguments, true);
        }

        return array_values($arguments);
    }

    /**
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
