<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;

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
            /* @phpstan-ignore-next-line */
            call_user_func_array(Closure::bind($closure, $this, get_class($this)), func_get_args());
            /* @phpstan-ignore-next-line */
            call_user_func_array(Closure::bind($next, $this, get_class($this)), func_get_args());
        };
    }

    /**
     * Call the given static `$closure` and chains the `$next` closure.
     */
    public static function fromStatic(Closure $closure, Closure $next): Closure
    {
        return static function () use ($closure, $next): void {
            /* @phpstan-ignore-next-line */
            call_user_func_array(Closure::bind($closure, null, self::class), func_get_args());
            /* @phpstan-ignore-next-line */
            call_user_func_array(Closure::bind($next, null, self::class), func_get_args());
        };
    }
}
