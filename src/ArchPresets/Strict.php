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
        $this->expectations[] = expect([
            'sleep',
            'usleep',
        ])->not->toBeUsed();

        foreach ($this->userNamespaces as $namespace) {
            $this->expectations[] = expect($namespace)
                ->toUseStrictTypes();
        }
    }
}
