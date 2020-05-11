<?php

declare(strict_types=1);

namespace Pest\Support;

/**
 * @internal
 */
final class Str
{
    /**
     * Checks if the given `$target` starts with the given `$search`.
     */
    public static function startsWith(string $target, string $search): bool
    {
        return substr($target, 0, strlen($search)) === $search;
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
}
