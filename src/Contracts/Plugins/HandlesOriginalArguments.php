<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

/**
 * @internal
 */
interface HandlesOriginalArguments
{
    /**
     * Adds original arguments before the Test Suite execution.
     *
     * @param  array<int, string>  $arguments
     */
    public function handleOriginalArguments(array $arguments): void;
}
