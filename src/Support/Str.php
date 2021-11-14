<?php

declare(strict_types=1);

namespace Pest\Support;

/**
 * @internal
 */
final class Str
{
    /**
     * Pool of alpha-numeric characters for generating (unsafe) random strings
     * from.
     */
    private const POOL = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * Create a (unsecure & non-cryptographically safe) random alpha-numeric
     * string value.
     *
     * @param int $length the length of the resulting randomized string
     *
     * @see https://github.com/laravel/framework/blob/4.2/src/Illuminate/Support/Str.php#L240-L242
     */
    public static function random(int $length = 16): string
    {
        return substr(str_shuffle(str_repeat(self::POOL, 5)), 0, $length);
    }

    /**
     * Checks if the given `$target` starts with the given `$search`.
     */
    public static function startsWith(string $target, string $search): bool
    {
        return str_starts_with($target, $search);
    }

    /**
     * Checks if the given `$target` ends with the given `$search`.
     */
    public static function endsWith(string $target, string $search): bool
    {
        $length = strlen($search);
        if ($length === 0) {
            return true;
        }

        return substr($target, -$length) === $search;
    }

    /**
     * Makes the given string evaluable by an `eval`.
     */
    public static function evaluable(string $code): string
    {
        $code = str_replace(' ', '_', $code);

        return (string) preg_replace('/[^A-Z_a-z0-9\\\\]/', '', $code);
    }
}
