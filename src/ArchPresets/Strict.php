<?php

declare(strict_types=1);

namespace Pest\ArchPresets;

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
        foreach ($this->userNamespaces as $namespace) {
            $expectations = [
                expect(['sleep', 'usleep'])->not->toBeUsed(),
                expect($namespace)->toUseStrictTypes(),
            ];

            $this->updateExpectations($expectations);
        }
    }
}
