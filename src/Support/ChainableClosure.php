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
     * Calls the given `$closure` and chains the the `$next` closure.
     */
    public static function from(Closure $closure, Closure $next): Closure
    {
        return function () use ($closure, $next): void {
            call_user_func_array(Closure::bind($closure, $this, get_class($this)), func_get_args());
            call_user_func_array(Closure::bind($next, $this, get_class($this)), func_get_args());
        };
    }
}
