<?php

declare(strict_types=1);

namespace Pest\Contracts;

/**
 * @internal
 */
interface Bootstrapper
{
    /**
     * Boots the bootstrapper.
     */
    public function boot(): void;
}
