<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;

/**
 * @internal
 */
final class Retry implements HandlesArguments
{
    use Concerns\HandleArguments;

    /**
     * {@inheritDoc}
     */
    public function handleArguments(array $arguments): array
    {
        if (! $this->hasArgument('--retry', $arguments)) {
            return $arguments;
        }

        $arguments = $this->popArgument('--retry', $arguments);

        $arguments = $this->pushArgument('--order-by=defects', $arguments);

        return $this->pushArgument('--stop-on-failure', $arguments);
    }
}
