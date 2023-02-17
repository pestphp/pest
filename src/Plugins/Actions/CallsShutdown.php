<?php

declare(strict_types=1);

namespace Pest\Plugins\Actions;

use Pest\Contracts\Plugins;
use Pest\Plugin\Loader;

/**
 * @internal
 */
final class CallsShutdown
{
    /**
     * Executes the Plugin action.
     *
     * Provides an opportunity for any plugins to shutdown.
     */
    public static function execute(): void
    {
        $plugins = Loader::getPlugins(Plugins\Shutdownable::class);

        /** @var Plugins\Shutdownable $plugin */
        foreach ($plugins as $plugin) {
            $plugin->shutdown();
        }
    }
}
