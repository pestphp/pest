<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

/**
 * @internal
 */
interface Shutdownable
{
    /**
     * Shutdowns the plugin.
     */
    public function shutdown(): void;
}
