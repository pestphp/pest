<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;
use Pest\Exceptions\ShouldNotHappen;

/**
 * @internal
 */
final class ChainableClosure
{
    /**
     * Calls the given `$closure` when the given condition is true, "bound" to the same object.
     */
    public static function boundWhen(object $self, Closure $condition, Closure $next): Closure
    {
        return function () use ($self, $condition, $next): void {
            if (! is_object($self)) { // @phpstan-ignore-line
                throw ShouldNotHappen::fromMessage('Object not provided for chainable closure.');
            }

            $boundCondition = Closure::bind($condition, $self, get_class($self));
            $boundNext = Closure::bind($next, $self, get_class($self));

            if ($boundCondition(...func_get_args())) {
                $boundNext(...func_get_args());
            }
        };
    }

    /**
     * Calls the given `$closure` and chains the `$next` closure, "bound" to the same object.
     */
    public static function bound(object $self, Closure $closure, Closure $next): Closure
    {
        return function () use ($self, $closure, $next): void {
            if (! is_object($self)) { // @phpstan-ignore-line
                throw ShouldNotHappen::fromMessage('Object not provided for chainable closure.');
            }

            $boundClosure = Closure::bind($closure, $self, get_class($self));
            $boundNext = Closure::bind($next, $self, get_class($self));

            $boundClosure(...func_get_args());
            $boundNext(...func_get_args());
        };
    }

    /**
     * Calls the given `$closure` and chains the `$next` closure, "unbound" of any object.
     */
    public static function unbound(Closure $closure, Closure $next): Closure
    {
        return function () use ($closure, $next): void {
            $closure(...func_get_args());
            $next(...func_get_args());
        };
    }

    /**
     * Call the given static `$closure` and chains the `$next` closure, "bound" to the same object statically.
     */
    public static function boundStatically(Closure $closure, Closure $next): Closure
    {
        return static function () use ($closure, $next): void {
            $boundClosure = Closure::bind($closure, null, self::class);
            $boundNext = Closure::bind($next, null, self::class);

            $boundClosure(...func_get_args());
            $boundNext(...func_get_args());
        };
    }
}
