<?php

declare(strict_types=1);

namespace Pest\ArchPresets;

use Pest\Arch\Contracts\ArchExpectation;
use Pest\Contracts\ArchPreset;
use Pest\PendingCalls\TestCall;

/**
 * @internal
 */
final class Base implements ArchPreset
{
    /**
     * Boots the arch preset.
     *
     * @param  array<string>  $baseNamespace
     */
    public function boot(TestCall $testCall, array $baseNamespace): TestCall|ArchExpectation
    {
        return $testCall
            ->expect(['dd', 'dump', 'ray', 'die', 'var_dump', 'sleep'])
            ->not
            ->toBeUsed();
    }
}
