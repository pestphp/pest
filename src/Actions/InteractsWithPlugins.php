<?php

declare(strict_types=1);

namespace Pest\Actions;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugin\Loader;

/**
 * @internal
 */
final class InteractsWithPlugins
{
    /**
     * Transform the input arguments by passing it to the relevant plugins.
     *
     * @param array<int, string> $argv
     *
     * @return array<int, string>
     */
    public static function handleArguments(array $argv): array
    {
        $plugins = Loader::getPlugins(HandlesArguments::class);

        /** @var HandlesArguments $plugin */
        foreach ($plugins as $plugin) {
            $argv = $plugin->handleArguments($argv);
        }

        return $argv;
    }

    /**
     * Provides an opportunity for any plugins that want
     * to provide additional output after test execution.
     */
    public static function addOutput(int $result): int
    {
        $plugins = Loader::getPlugins(AddsOutput::class);

        /** @var AddsOutput $plugin */
        foreach ($plugins as $plugin) {
            $result = $plugin->addOutput($result);
        }

        return $result;
    }
}
