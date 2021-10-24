<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;

final class Extendable
{
    /**
     * Creates a new extendable instance.
     */
    public function __construct(
        private string $extendableClass
    ) {
        // ..
    }

    /**
     * Register a custom extend.
     */
    public function extend(string $name, Closure $extend): void
    {
        $this->extendableClass::extend($name, $extend);
    }
}
