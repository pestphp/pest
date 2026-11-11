<?php

declare(strict_types=1);

namespace Pest\Plugins\Actions;

use Pest\Contracts\Plugins;
use Pest\Plugin\Loader;

/**
 * @internal
 */
final class CallsHandleOriginalArguments
{
    /**
     * @param  array<int, string>  $argv
     */
    public static function execute(array $argv): void
    {
        $plugins = Loader::getPlugins(Plugins\HandlesOriginalArguments::class);

        /** @var Plugins\HandlesOriginalArguments $plugin */
        foreach ($plugins as $plugin) {
            $plugin->handleOriginalArguments($argv);
        }
    }
}
