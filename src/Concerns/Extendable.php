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

    /** @var array<string, array<Closure>> */
    private static $decorators = [];

    /**
     * Register a custom extend.
     */
    public static function extend(string $name, Closure $extend): void
    {
        static::$extends[$name] = $extend;
    }

    public static function decorate(string $name, Closure $decorator): void
    {
        static::$decorators[$name][] = $decorator;
    }

    /**
     * Checks if extend is registered.
     */
    public static function hasExtend(string $name): bool
    {
        return array_key_exists($name, static::$extends);
    }

    /**
     * Checks if decorator are registered.
     */
    public static function hasDecorators(string $name): bool
    {
        return array_key_exists($name, static::$decorators);
    }

    /**
     * @return array<int, Closure>
     */
    public function decorators(string $name, object $context, string $scope): array
    {
        if (!self::hasDecorators($name)) {
            return [];
        }

        $decorators = [];
        foreach (self::$decorators[$name] as $decorator) {
            //@phpstan-ignore-next-line
            $decorators[] = $decorator->bindTo($context, $scope);
        }

        return $decorators;
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
            throw new BadMethodCallException("$method is not a callable method name.");
        }

        /** @var Closure $extend */
        $extend = static::$extends[$method]->bindTo($this, static::class);

        return $extend(...$parameters);
    }
}
