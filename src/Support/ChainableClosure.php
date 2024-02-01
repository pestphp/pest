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
    public static function boundWhen(Closure $condition, Closure $next): Closure
    {
        return function (...$arguments) use ($condition, $next): void {
            if (! is_object($this)) { // @phpstan-ignore-line
                throw ShouldNotHappen::fromMessage('$this not bound to chainable closure.');
            }

            if (\Pest\Support\Closure::bind($condition, $this, self::class)(...$arguments)) {
                \Pest\Support\Closure::bind($next, $this, self::class)(...$arguments);
            }
        };
    }

    /**
     * Calls the given `$closure` and chains the `$next` closure, "bound" to the same object.
     */
    public static function bound(Closure $closure, Closure $next): Closure
    {
        return function (...$arguments) use ($closure, $next): void {
            if (! is_object($this)) { // @phpstan-ignore-line
                throw ShouldNotHappen::fromMessage('$this not bound to chainable closure.');
            }

            \Pest\Support\Closure::bind($closure, $this, self::class)(...$arguments);
            \Pest\Support\Closure::bind($next, $this, self::class)(...$arguments);
        };
    }

    /**
     * Calls the given `$closure` and chains the `$next` closure, "unbound" of any object.
     */
    public static function unbound(Closure $closure, Closure $next): Closure
    {
        return function (...$arguments) use ($closure, $next): void {
            $closure(...$arguments);
            $next(...$arguments);
        };
    }

    /**
     * Call the given static `$closure` and chains the `$next` closure, "bound" to the same object statically.
     */
    public static function boundStatically(Closure $closure, Closure $next): Closure
    {
        return static function (...$arguments) use ($closure, $next): void {
            \Pest\Support\Closure::bind($closure, null, self::class)(...$arguments);
            \Pest\Support\Closure::bind($next, null, self::class)(...$arguments);
        };
    }
}
