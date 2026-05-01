<?php

declare(strict_types=1);

namespace Pest\Contracts;

/**
 * @internal
 */
interface Restarter
{
    /**
     * Re-execs the PHP process when conditions warrant it.
     *
     * @param  array<int, string>  $arguments
     */
    public function maybeRestart(string $projectRoot, array $arguments): void;
}
