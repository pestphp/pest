<?php

declare(strict_types=1);

namespace Pest\ArchPresets;

/**
 * @internal
 */
final class Base extends AbstractPreset
{
    /**
     * Executes the arch preset.
     */
    public function execute(): void
    {
        $this->expectations[] = expect(['dd', 'dump', 'ray', 'die', 'var_dump', 'sleep', 'eval', 'ini_set'])
            ->not
            ->toBeUsed();
    }
}
