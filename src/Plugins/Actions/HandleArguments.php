<?php

namespace Pest\Plugins\Actions;

use Pest\Contracts\Plugins;
use Pest\Plugin\Loader;

/**
 * @internal
 */
final class HandleArguments
{
    /**
     * Executes the Plugin action.
     *
     * Transform the input arguments by passing it to the relevant plugins.
     *
     * @param array<int, string> $argv
     *
     * @return array<int, string>
     */
    public function __invoke(array $argv): array
    {
        $plugins = Loader::getPlugins(Plugins\HandlesArguments::class);

        /** @var Plugins\HandlesArguments $plugin */
        foreach ($plugins as $plugin) {
            $argv = $plugin->handleArguments($argv);
        }

        return $argv;
    }
}
