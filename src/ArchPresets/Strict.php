<?php

declare(strict_types=1);

namespace Pest\ArchPresets;

use Pest\Arch\Contracts\ArchExpectation;
use Pest\Contracts\ArchPreset;
use Pest\PendingCalls\TestCall;

/**
 * @internal
 */
final class Strict implements ArchPreset
{
    /**
     * Boots the arch preset.
     *
     * @param  array<string>  $baseNamespaces
     */
    public function boot(TestCall $testCall, array $baseNamespaces): TestCall|ArchExpectation
    {
        return $testCall
            ->expect($baseNamespaces)
            ->each
            ->toUseStrictTypes();
    }
}
