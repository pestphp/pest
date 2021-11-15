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
     * Calls the given `$closure` and chains the `$next` closure.
     */
    public static function from(Closure $closure, Closure $next): Closure
    {
        return function () use ($closure, $next): void {
            if (!is_object($this)) { // @phpstan-ignore-line
                throw ShouldNotHappen::fromMessage('$this not bound to chainable closure.');
            }

            call_user_func_array(Closure::bind($closure, $this, $this::class), func_get_args());
            call_user_func_array(Closure::bind($next, $this, $this::class), func_get_args());
        };
    }

    /**
     * Call the given static `$closure` and chains the `$next` closure.
     */
    public static function fromStatic(Closure $closure, Closure $next): Closure
    {
        return static function () use ($closure, $next): void {
            call_user_func_array(Closure::bind($closure, null, self::class), func_get_args());
            call_user_func_array(Closure::bind($next, null, self::class), func_get_args());
        };
    }
}
