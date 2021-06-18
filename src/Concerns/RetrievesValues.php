<?php

declare(strict_types=1);

namespace Pest\Concerns;

/**
 * @internal
 */
trait RetrievesValues
{
    /**
     * @template TValue
     *
     * Safely retrieve the value at the given key from an object or array.
     *
     * @param array<string, TValue>|object $value
     * @param TValue|null               $default
     *
     * @return TValue|null
     */
    private function retrieve(string $key, $value, $default = null)
    {
        if (is_array($value)) {
            return $value[$key] ?? $default;
        }

        // @phpstan-ignore-next-line
        return $value->$key ?? $default;
    }
}
