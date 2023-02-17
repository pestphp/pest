<?php

declare(strict_types=1);

namespace Pest\Plugins\Actions;

use Pest\Contracts\Plugins;
use Pest\Plugin\Loader;

/**
 * @internal
 */
final class CallsAddsOutput
{
    /**
     * Executes the Plugin action.
     *
     * Provides an opportunity for any plugins that want to provide additional output after test execution.
     */
    public static function execute(int $exitCode): int
    {
        $plugins = Loader::getPlugins(Plugins\AddsOutput::class);

        /** @var Plugins\AddsOutput $plugin */
        foreach ($plugins as $plugin) {
            $exitCode = $plugin->addOutput($exitCode);
        }

        return $exitCode;
    }
}
