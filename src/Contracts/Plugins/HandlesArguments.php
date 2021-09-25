<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

/**
 * @internal
 */
interface HandlesArguments
{
    /**
     * Allows to handle custom command line arguments.
     *
     * @param array<int, string> $arguments
     *
     * @return array<int, string> the updated list of arguments
     */
    public function handleArguments(array $arguments): array;
}
