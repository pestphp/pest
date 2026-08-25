<?php

declare(strict_types=1);

namespace Pest\Plugins\Actions;

use Pest\Contracts\Plugins;
use Pest\Plugin\Loader;

/**
 * @internal
 */
final class CallsHandleArguments
{
    /**
     * @param  array<int, string>  $argv
     * @return array<int, string>
     */
    public static function execute(array $argv): array
    {
        $plugins = Loader::getPlugins(Plugins\HandlesArguments::class);

        /** @var Plugins\HandlesArguments $plugin */
        foreach ($plugins as $plugin) {
            $argv = $plugin->handleArguments($argv);
        }

        return $argv;
    }
}
