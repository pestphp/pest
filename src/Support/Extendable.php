<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;

final class Extendable
{
    /**
     * The extendable class.
     *
     * @var string
     */
    private $extendableClass;

    /**
     * Creates a new extendable instance.
     */
    public function __construct(string $extendableClass)
    {
        $this->extendableClass = $extendableClass;
    }

    /**
     * Register a custom extend.
     */
    public function extend(string $name, Closure $extend): void
    {
        $this->extendableClass::extend($name, $extend);
    }
}
