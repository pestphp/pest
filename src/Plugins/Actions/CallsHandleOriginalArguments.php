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
     * Executes the Plugin action.
     *
     * Transform the input arguments by passing it to the relevant plugins.
     *
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
