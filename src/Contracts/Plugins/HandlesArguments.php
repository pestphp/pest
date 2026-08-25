<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

/**
 * @internal
 */
interface HandlesArguments
{
    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    public function handleArguments(array $arguments): array;
}
