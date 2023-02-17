<?php

declare(strict_types=1);

namespace Pest\Plugins\Actions;

use Pest\Contracts\Plugins;
use Pest\Plugin\Loader;

/**
 * @internal
 */
final class CallsBoot
{
    /**
     * Executes the Plugin action.
     *
     * Provides an opportunity for any plugins to boot.
     */
    public static function execute(): void
    {
        $plugins = Loader::getPlugins(Plugins\Bootable::class);

        /** @var Plugins\Bootable $plugin */
        foreach ($plugins as $plugin) {
            $plugin->boot();
        }
    }
}
