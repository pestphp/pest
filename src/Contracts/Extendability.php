<?php

declare(strict_types=1);

namespace Pest\Contracts;

use Closure;

interface Extendability
{
    /**
     * Register a custom extend.
     */
    public function extend(string $name, Closure $extend): void;
}
