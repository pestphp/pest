<?php

declare(strict_types=1);

namespace Pest\Concerns;

use BadMethodCallException;
use Closure;

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
     *
     * @throws BadMethodCallException
     */
    public function __call(string $method, array $parameters)
    {
        if (!static::hasExtend($method)) {
            throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $method));
        }

        $extend = static::$extends[$method]->bindTo($this, static::class);

        return $extend(...$parameters);
    }
}
