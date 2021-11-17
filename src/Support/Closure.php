<?php

declare(strict_types=1);

namespace Pest\Support;

use Pest\Exceptions\ShouldNotHappen;

/**
 * @internal
 */
final class Closure
{
    public static function safeBind(\Closure|null $closure, ?object $newThis, object|string|null $newScope = 'static'): \Closure
    {
        if ($closure == null) {
            throw ShouldNotHappen::fromMessage('Could not bind null closure.');
        }

        $closure = \Closure::bind($closure, $newThis, $newScope);

        if ($closure == false) {
            throw ShouldNotHappen::fromMessage('Could not bind closure.');
        }

        return $closure;
    }
}
