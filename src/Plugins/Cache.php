<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Concerns\HandleArguments;
use PHPUnit\TextUI\CliArguments\Builder as CliConfigurationBuilder;
use PHPUnit\TextUI\CliArguments\XmlConfigurationFileFinder;
use PHPUnit\TextUI\XmlConfiguration\DefaultConfiguration;
use PHPUnit\TextUI\XmlConfiguration\Loader;

/**
 * @internal
 */
final class Cache implements HandlesArguments
{
    use HandleArguments;

    /**
     * The temporary folder.
     */
    private const TEMPORARY_FOLDER = __DIR__
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'.temp';

    /**
     * Handles the arguments, adding the cache directory and the cache result arguments.
     */
    public function handleArguments(array $arguments): array
    {
        if (! $this->hasArgument('--cache-directory', $arguments)) {

            $cliConfiguration = (new CliConfigurationBuilder)->fromParameters([]);
            $configurationFile = (new XmlConfigurationFileFinder)->find($cliConfiguration);
            $xmlConfiguration = DefaultConfiguration::create();

            if (is_string($configurationFile)) {
                $xmlConfiguration = (new Loader)->load($configurationFile);
            }

            if (! $xmlConfiguration->phpunit()->hasCacheDirectory()) {
                $arguments = $this->pushArgument('--cache-directory', $arguments);
                $arguments = $this->pushArgument((string) realpath(self::TEMPORARY_FOLDER), $arguments);
            }
        }

        if (! $this->hasArgument('--parallel', $arguments)) {
            return $this->pushArgument('--cache-result', $arguments);
        }

        return $arguments;
    }
}
