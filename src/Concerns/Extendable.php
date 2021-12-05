<?php

declare(strict_types=1);

namespace Pest\Concerns;

use Closure;

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

    /**
     * Register a new extend.
     */
    public function extend(string $name, Closure $extend): void
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
}
