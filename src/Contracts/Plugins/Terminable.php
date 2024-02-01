<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

/**
 * @internal
 */
interface Terminable
{
    /**
     * Terminates the plugin.
     */
    public function terminate(): void;
}
