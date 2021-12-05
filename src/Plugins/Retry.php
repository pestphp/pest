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
     * Whether it should show retry or not.
     */
    public static bool $retrying = false;

    /**
     * {@inheritDoc}
     */
    public function handleArguments(array $arguments): array
    {
        self::$retrying = $this->hasArgument('--retry', $arguments);

        return $this->popArgument('--retry', $arguments);
    }
}
