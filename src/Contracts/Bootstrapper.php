<?php

declare(strict_types=1);

namespace Pest\Contracts;

/**
 * @internal
 */
interface Bootstrapper
{
    public function boot(): void;
}
