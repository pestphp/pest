<?php

declare(strict_types=1);

namespace Pest\Contracts;

/**
 * @internal
 */
interface Restarter
{
    /**
     * @param  array<int, string>  $arguments
     */
    public function maybeRestart(string $projectRoot, array $arguments): void;
}
