<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Concerns\HandleArguments;

/**
 * @internal
 */
final class JUnit implements HandlesArguments
{
    use HandleArguments;

    /**
     * Handles the arguments, adding the cache directory and the cache result arguments.
     */
    public function handleArguments(array $arguments): array
    {
        if (! $this->hasArgument('--log-junit', $arguments)) {
            return $arguments;
        }

        $logUnitArgument = null;

        $arguments = array_filter($arguments, function (string $argument) use (&$logUnitArgument): bool {
            if (str_starts_with($argument, '--log-junit')) {
                $logUnitArgument = $argument;

                return false;
            }

            return true;
        });

        assert(is_string($logUnitArgument));

        $arguments[] = $logUnitArgument;

        return array_values($arguments);
    }
}
