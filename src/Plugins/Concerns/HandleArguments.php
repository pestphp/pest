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
     * @param array<int, string> $arguments
     */
    public function hasArgument(string $argument, array $arguments): bool
    {
        return in_array($argument, $arguments, true);
    }

    /**
     * Pops the given argument from the arguments.
     *
     * @param array<int, string> $arguments
     *
     * @return array<int, string>
     */
    public function popArgument(string $argument, array $arguments): array
    {
        $arguments = array_flip($arguments);

        unset($arguments[$argument]);

        return array_flip($arguments);
    }
}
