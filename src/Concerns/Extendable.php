<?php

declare(strict_types=1);

namespace Pest\Concerns;

use Closure;
use Pest\HigherOrderExpectation;

/**
 * @internal
 */
trait Extendable
{
    /**
     * @var array<string, Closure>
     */
    private static $extends = [];

    /**
     * Register a custom extend.
     */
    public static function extend(string $name, Closure $extend): void
    {
        static::$extends[$name] = $extend;
    }

    /**
     * Checks if extend is registered.
     */
    public static function hasExtend(string $name): bool
    {
        return array_key_exists($name, static::$extends);
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param array<int, mixed> $parameters
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        if (!static::hasExtend($method)) {
            return new HigherOrderExpectation($this, $method, $parameters);
        }

        /** @var Closure $extend */
        $extend = static::$extends[$method]->bindTo($this, static::class);

        return $extend(...$parameters);
    }
}
