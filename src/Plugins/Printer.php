<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;

/**
 * @internal
 */
final class Printer implements HandlesArguments
{
    use Concerns\HandleArguments;

    /**
     * {@inheritDoc}
     */
    public function handleArguments(array $arguments): array
    {
        if (! array_key_exists('COLLISION_PRINTER', $_SERVER)) {
            return $arguments;
        }

        if (in_array('--no-output', $arguments, true)) {
            return $arguments;
        }

        return $this->pushArgument('--no-output', $arguments);
    }
}
