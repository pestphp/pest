<?php

declare(strict_types=1);

namespace Pest\Support;

use Fidry\CpuCoreCounter\CpuCoreCounter;

/**
 * @internal
 */
final class Cpu
{
    public static function cores(int $fallback = 4): int
    {
        return (new CpuCoreCounter)->getCountWithFallback($fallback);
    }
}
