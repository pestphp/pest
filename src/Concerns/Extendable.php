<?php

declare(strict_types=1);

namespace Pest\Concerns;

use BadMethodCallException;
use Closure;
use Pest\Expectation;

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
     * Checks if given extend name is registered.
     */
    public static function hasExtend(string $name): bool
    {
        return array_key_exists($name, static::$extends);
    }

    /**
     * Register a pipe to be applied before an expectation is checked.
     */
    public static function pipe(string $name, Closure $handler): void
    {
        self::$pipes[$name][] = $handler;
    }

    /**
     * Register an interceptor that should replace an existing expectation.
     *
     * @param class-string|Closure(mixed $value, mixed ...$arguments): bool $filter
     * @param Closure(mixed ...$arguments): void $handler
     */
    public static function intercept(string $name, string|Closure $filter, Closure $handler): void
    {
        if (is_string($filter)) {
            $filter = fn ($value, ...$arguments): bool => $value instanceof $filter;
        }

        self::pipe($name, function ($next, ...$arguments) use ($handler, $filter): void {
            /* @phpstan-ignore-next-line  */
            if (!$filter($this->value, ...$arguments)) {
                $next();

                return;
            }

            /** @phpstan-ignore-next-line  */
            $handler = $handler->bindTo($this, $this::class);

            $handler(...$arguments);
        });
    }

    /**
     * Checks if pipes are registered for a given expectation.
     */
    private static function hasPipes(string $name): bool
    {
        return array_key_exists($name, static::$pipes);
    }

    /**
     * Gets the pipes that have been registered for a given expectation and binds them to a context and a scope.
     *
     * @return array<int, Closure>
     */
    private function pipes(string $name, object $context, string $scope): array
    {
        if (!self::hasPipes($name)) {
            return [];
        }

        $pipes = [];
        foreach (self::$pipes[$name] as $pipe) {
            $pipe = $pipe->bindTo($context, $scope);

            if ($pipe instanceof Closure) {
                $pipes[] = $pipe;
            }
        }

        return $pipes;
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
