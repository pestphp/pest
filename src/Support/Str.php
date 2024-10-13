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
     * @var string
     */
    private const PREFIX = '__pest_evaluable_';

    /**
     * Create a (unsecure & non-cryptographically safe) random alpha-numeric
     * string value.
     *
     * @param  int  $length  the length of the resulting randomized string
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

        return $search === substr($target, -$length);
    }

    /**
     * Makes the given string evaluable by an `eval`.
     */
    public static function evaluable(string $code): string
    {
        $code = str_replace('_', '__', $code);

        $code = self::PREFIX.str_replace(' ', '_', $code);

        // sticks to PHP8.2 function naming rules https://www.php.net/manual/en/functions.user-defined.php
        return (string) preg_replace('/[^a-zA-Z0-9_\x80-\xff]/', '_', $code);
    }

    /**
     * Get the portion of a string before the last occurrence of a given value.
     */
    public static function beforeLast(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = mb_strrpos($subject, $search);

        if ($pos === false) {
            return $subject;
        }

        return substr($subject, 0, $pos);
    }

    /**
     * Returns the content after the given "search".
     */
    public static function after(string $subject, string $search): string
    {
        return $search === '' ? $subject : array_reverse(explode($search, $subject, 2))[0];
    }

    /**
     * Determine if a given value is a valid UUID.
     */
    public static function isUuid(string $value): bool
    {
        return preg_match('/^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$/iD', $value) > 0;
    }

    /**
     * Creates a describe block as `$describeDescription` → `$testDescription` format.
     *
     * @param  array<int, string>  $describeDescriptions
     */
    public static function describe(array $describeDescriptions, string $testDescription): string
    {
        $descriptionComponents = [...$describeDescriptions, $testDescription];

        return sprintf(str_repeat('`%s` → ', count($describeDescriptions)).'%s', ...$descriptionComponents);
    }

    /**
     * Determine if a given value is a valid URL.
     */
    public static function isUrl(string $value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_URL);
    }
}
