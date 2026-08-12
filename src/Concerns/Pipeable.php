<?php

declare(strict_types=1);

namespace Pest\Concerns;

use Closure;

/**
 * @internal
 */
trait Pipeable
{
    /**
     * @var array<string, array<Closure(Closure, mixed ...$arguments): void>>
     */
    private static array $pipes = [];

    /**
     * @var array<string, array<Closure(Closure, mixed ...$arguments): void>>
     */
    private static array $interceptors = [];

    public function pipe(string $name, Closure $pipe): void
    {
        self::$pipes[$name][] = $pipe;
    }

    /**
     * @param  string|Closure(mixed $value, mixed ...$arguments):bool  $filter
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
     * @return array<int, Closure>
     */
    private function pipes(string $name, object $context, string $scope): array
    {
        return array_map(fn (Closure $pipe): Closure => $pipe->bindTo($context, $scope), self::$pipes[$name] ?? []);
    }
}
