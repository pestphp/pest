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
        $this->expectations[] = expect([
            'debug_zval_dump',
            'debug_backtrace',
            'debug_print_backtrace',
            'dd',
            'ddd',
            'dump',
            'ray',
            'die',
            'goto',  
            'var_dump',
            'phpinfo',
            'echo',
            'print',
            'print_r',
            'var_export',
        ])->not->toBeUsed();
    }
}
