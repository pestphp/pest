<?php

declare(strict_types=1);

namespace Pest\Contracts;

use Pest\Arch\Contracts\ArchExpectation;
use Pest\PendingCalls\TestCall;

/**
 * @internal
 */
interface ArchPreset
{
    /**
     * Boots the arch preset.
     *
     * @param  array<int, string>  $baseNamespaces
     */
    public function boot(TestCall $testCall, array $baseNamespaces): TestCall|ArchExpectation;
}
