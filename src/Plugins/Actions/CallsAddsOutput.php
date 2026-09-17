<?php

declare(strict_types=1);

namespace Pest\Plugins\Actions;

use Pest\Contracts\Plugins;
use Pest\Plugin\Loader;

final class CallsAddsOutput
{
    public static function execute(int $exitCode): int
    {
        $plugins = Loader::getPlugins(Plugins\AddsOutput::class);

        foreach ($plugins as $plugin) {
            assert($plugin instanceof Plugins\AddsOutput);

            $exitCode = $plugin->addOutput($exitCode);
        }

        foreach (Loader::getPlugins(Plugins\ObservesExitCode::class) as $plugin) {
            assert($plugin instanceof Plugins\ObservesExitCode);

            $plugin->observeExitCode($exitCode);
        }

        return $exitCode;
    }
}
