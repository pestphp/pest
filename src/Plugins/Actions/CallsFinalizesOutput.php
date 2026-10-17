<?php

declare(strict_types=1);

namespace Pest\Plugins\Actions;

use Pest\Contracts\Plugins;
use Pest\Plugin\Loader;

/**
 * @internal
 */
final class CallsFinalizesOutput
{
    public static function execute(int $exitCode): int
    {
        $plugins = Loader::getPlugins(Plugins\FinalizesOutput::class);

        /** @var Plugins\FinalizesOutput $plugin */
        foreach ($plugins as $plugin) {
            $exitCode = $plugin->finalizeOutput($exitCode);
        }

        return $exitCode;
    }
}
