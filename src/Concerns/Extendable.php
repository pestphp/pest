<?php

declare(strict_types=1);

namespace Pest\Concerns;

use Closure;

/**
 * @internal
 *
 * @template T of object
 */
trait Extendable
{
    /**
     * @var array<string, Closure>
     */
    private static array $extends = [];

    /**
     * @param-closure-this T $extend
     */
    public function extend(string $name, Closure $extend): void
    {
        static::$extends[$name] = $extend;
    }

    public static function hasExtend(string $name): bool
    {
        return array_key_exists($name, static::$extends);
    }
}
