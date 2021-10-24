<?php

namespace Pest\Plugins\Actions;

use Pest\Contracts\Plugins;
use Pest\Plugin\Loader;

/**
 * @internal
 */
final class AddsOutput
{
    /**
     * Executes the Plugin action.
     *
     * Provides an opportunity for any plugins that want
     * to provide additional output after test execution.
     */
    public function __invoke(int $exitCode): int
    {
        $plugins = Loader::getPlugins(Plugins\AddsOutput::class);

        /** @var Plugins\AddsOutpu $plugin */
        foreach ($plugins as $plugin) {
            $exitCode = $plugin->addOutput($exitCode);
        }

        return $exitCode;
    }
}
