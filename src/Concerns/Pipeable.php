<?php

declare(strict_types=1);

namespace Pest\Concerns;

use Closure;
use Pest\Expectation;

/**
 * @internal
 */
trait Pipeable
{
    /**
     * The list of pipes.
     *
     * @var array<string, array<Closure(Closure, mixed ...$arguments): void>>
     */
    private static array $pipes = [];

    /**
     * The list of interceptors.
     *
     * @var array<string, array<Closure(Closure, mixed ...$arguments): void>>
     */
    private static array $interceptors = [];

    /**
     * Register a pipe to be applied before an expectation is checked.
     */
    public function pipe(string $name, Closure $pipe): void
    {
        self::$pipes[$name][] = $pipe;
    }

    /**
     * Register an interceptor that should replace an existing expectation.
     *
     * @param string|Closure(mixed $value, mixed ...$arguments):bool $filter
     */
    public function intercept(string $name, string|Closure $filter, Closure $handler): void
    {
        if (is_string($filter)) {
            $filter = fn ($value): bool => $value instanceof $filter;
        }

        self::$interceptors[$name][] = $handler;

        $this->pipe($name, function ($next, ...$arguments) use ($handler, $filter): void {
            /* @phpstan-ignore-next-line */
            if ($filter($this->value, ...$arguments)) {
                // @phpstan-ignore-next-line
                $handler->bindTo($this, $this::class)(...$arguments);

                return;
            }

            $next();
        });
    }

    /**
     * Get th list of pipes by the given name.
     *
     * @return array<int, Closure>
     */
    private function pipes(string $name, object $context, string $scope): array
    {
        return array_map(fn (Closure $pipe): \Closure => $pipe->bindTo($context, $scope), self::$pipes[$name] ?? []);
    }
}
