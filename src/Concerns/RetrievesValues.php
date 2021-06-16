<?php

declare(strict_types=1);

namespace Pest\Concerns;

/**
 * @internal
 */
trait RetrievesValues
{
    /**
     * Safely retrieve the value at the given key from an object or array.
     *
     * @param array<mixed>|object $value
     * @param mixed               $default
     *
     * @return mixed
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
