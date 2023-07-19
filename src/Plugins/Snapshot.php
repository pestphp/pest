<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Exceptions\InvalidOption;
use Pest\TestSuite;

/**
 * @internal
 */
final class Snapshot implements HandlesArguments
{
    use Concerns\HandleArguments;

    /**
     * {@inheritDoc}
     */
    public function handleArguments(array $arguments): array
    {
        if (! $this->hasArgument('--update-snapshots', $arguments)) {
            return $arguments;
        }

        if ($this->hasArgument('--parallel', $arguments)) {
            throw new InvalidOption('The [--update-snapshots] option is not supported when running in parallel.');
        }

        TestSuite::getInstance()->snapshots->flush();

        return $this->popArgument('--update-snapshots', $arguments);
    }
}
