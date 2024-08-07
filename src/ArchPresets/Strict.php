<?php

declare(strict_types=1);

namespace Pest\ArchPresets;

use Pest\Arch\Contracts\ArchExpectation;
use Pest\Expectation;

/**
 * @internal
 */
final class Strict extends AbstractPreset
{
    /**
     * Executes the arch preset.
     */
    public function execute(): void
    {
        $this->eachUserNamespace(
            fn (Expectation $namespace): ArchExpectation => $namespace->toUseStrictTypes(),
            fn (Expectation $namespace): ArchExpectation => $namespace->classes()->toBeFinal(),
            fn (Expectation $namespace): ArchExpectation => $namespace->classes()->not->toBeAbstract(),
        );

        $this->expectations[] = expect([
            'sleep',
            'usleep',
        ])->not->toBeUsed();
    }
}
