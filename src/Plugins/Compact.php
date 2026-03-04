<?php

declare(strict_types=1);

namespace Pest\Plugins;

use NunoMaduro\Collision\Adapters\Phpunit\Printers\DefaultPrinter;
use Pest\Contracts\Plugins\HandlesArguments;

/**
 * @internal
 */
final class Compact implements HandlesArguments
{
    use Concerns\HandleArguments;

    /**
     * {@inheritDoc}
     */
    public function handleArguments(array $arguments): array
    {
        if (! $this->hasArgument('--compact', $arguments)) {
            return $arguments;
        }

        DefaultPrinter::compact(true);

        return $this->popArgument('--compact', $arguments);
    }
}
