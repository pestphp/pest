<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Exceptions\InvalidOption;

/**
 * @internal
 */
final class Profile implements HandlesArguments
{
    use Concerns\HandleArguments;

    /**
     * {@inheritDoc}
     */
    public function handleArguments(array $arguments): array
    {
        if (! $this->hasArgument('--profile', $arguments)) {
            return $arguments;
        }

        if ($this->hasArgument('--parallel', $arguments)) {
            throw new InvalidOption('The [--profile] option is not supported when running in parallel.');
        }

        return $arguments;
    }
}
