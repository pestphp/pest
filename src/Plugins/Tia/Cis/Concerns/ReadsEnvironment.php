<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\Cis\Concerns;

/**
 * @internal
 */
trait ReadsEnvironment
{
    private function environment(string $name): ?string
    {
        $value = getenv($name);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
