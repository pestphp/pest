<?php

declare(strict_types=1);

namespace Pest\Support;

/**
 * @internal
 */
final class Backtrace
{
    /**
     * Returns the filename that called the current function/method.
     */
    public static function file(): string
    {
        return debug_backtrace()[1]['file'];
    }

    /**
     * Returns the dirname that called the current function/method.
     */
    public static function dirname(): string
    {
        return dirname(debug_backtrace()[1]['file']);
    }

    /**
     * Returns the line that called the current function/method.
     */
    public static function line(): int
    {
        return debug_backtrace()[1]['line'];
    }
}
