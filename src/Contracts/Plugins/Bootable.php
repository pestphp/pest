<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

/**
 * @internal
 */
interface Bootable
{
    /**
     * Boots the plugin.
     */
    public function boot(): void;
}
