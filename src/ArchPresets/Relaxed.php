<?php

declare(strict_types=1);

namespace Pest\ArchPresets;

use Pest\Arch\Contracts\ArchExpectation;
use Pest\Expectation;

/**
 * @internal
 */
final class Relaxed extends AbstractPreset
{
    /**
     * Executes the arch preset.
     */
    public function execute(): void
    {
        $this->eachUserNamespace(
            fn (Expectation $namespace): ArchExpectation => $namespace->not->toUseStrictTypes(),
            fn (Expectation $namespace): ArchExpectation => $namespace->classes()->not->toBeFinal(),
            fn (Expectation $namespace): ArchExpectation => $namespace->classes()->not->toHavePrivateMethods(),
        );
    }
}
