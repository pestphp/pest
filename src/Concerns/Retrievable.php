<?php

declare(strict_types=1);

namespace Pest\Concerns;

/**
 * @internal
 */
trait Retrievable
{
    /**
     * @template TRetrievableValue
     *
     * Safely retrieve the value at the given key from an object or array.
     * @template TRetrievableValue
     *
     * @param  array<string, TRetrievableValue>|object  $value
     * @param  TRetrievableValue|null  $default
     * @return TRetrievableValue|null
     */
    private function retrieve(string $key, mixed $value, mixed $default = null): mixed
    {
        if (is_array($value)) {
            return $value[$key] ?? $default;
        }

        // @phpstan-ignore-next-line
        return $value->$key ?? $default;
    }
}
