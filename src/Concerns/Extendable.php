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
     * The list of extends.
     *
     * @var array<string, Closure>
     */
    private static array $extends = [];

    /** @var array<string, array<Closure>> */
    private static array $pipes = [];

    /**
     * Register a new extend.
     */
    public static function extend(string $name, Closure $extend): void
    {
        static::$extends[$name] = $extend;
    }

    /**
     * Register a pipe to be applied before an expectation is checked.
     */
    public static function pipe(string $name, Closure $pipe): void
    {
        self::$pipes[$name][] = $pipe;
    }

    /**
     * Recister an interceptor that should replace an existing expectation.
     */
    public static function intercept(string $name, string|Closure $filter, Closure $handler): void
    {
        if (is_string($filter)) {
            $filter = function ($value) use ($filter): bool {
                return $value instanceof $filter;
            };
        }

        //@phpstan-ignore-next-line
        self::pipe($name, function ($next, ...$arguments) use ($handler, $filter) {
            //@phpstan-ignore-next-line
            if ($filter($this->value)) {
                //@phpstan-ignore-next-line
                $handler->bindTo($this, get_class($this))(...$arguments);

                return;
            }

            $next();
        });
    }

    /**
     * Checks if given extend name is registered.
     */
    public static function hasExtend(string $name): bool
    {
        return array_key_exists($name, static::$extends);
    }

    /**
     * Checks if pipes are registered for a given expectation.
     */
    public static function hasPipes(string $name): bool
    {
        return array_key_exists($name, static::$pipes);
    }

    /**
     * @return array<int, Closure>
     */
    public function pipes(string $name, object $context, string $scope): array
    {
        if (!self::hasPipes($name)) {
            return [];
        }

        $decorators = [];
        foreach (self::$pipes[$name] as $decorator) {
            $decorators[] = $decorator->bindTo($context, $scope);
        }

        //@phpstan-ignore-next-line
        return $decorators;
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param array<int, mixed> $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (!static::hasExtend($method)) {
            throw new BadMethodCallException("$method is not a callable method name.");
        }

        /** @var Closure $extend */
        $extend = static::$extends[$method]->bindTo($this, static::class);

        return $extend(...$parameters);
    }
}
