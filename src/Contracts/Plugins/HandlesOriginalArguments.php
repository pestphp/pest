<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

/**
 * @internal
 */
interface HandlesOriginalArguments
{
    /**
     * @param  array<int, string>  $arguments
     */
    public function handleOriginalArguments(array $arguments): void;
}
