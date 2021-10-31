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

    /**
     * Register pipe to be applied to the given expectation.
     */
    public function pipe(string $name, Closure $handler): void
    {
        $this->extendableClass::pipe($name, $handler);
    }

    public function intercept(string $name, string|Closure $filter, Closure $handler): void
    {
        $this->extendableClass::intercept($name, $filter, $handler);
    }
}
