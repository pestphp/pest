<?php

declare(strict_types=1);

namespace Pest\Plugins;

use DOMDocument;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Contracts\Plugins\Terminable;
use Pest\Plugins\Concerns\HandleArguments;
use PHPUnit\TextUI\CliArguments\Builder as CliConfigurationBuilder;
use PHPUnit\TextUI\CliArguments\XmlConfigurationFileFinder;

/**
 * @internal
 */
final class Configuration implements HandlesArguments, Terminable
{
    use HandleArguments;

    /**
     * The base PHPUnit file.
     */
    public const BASE_PHPUNIT_FILE = __DIR__
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'resources/base-phpunit.xml';

    /**
     * Handles the arguments, adding the cache directory and the cache result arguments.
     */
    public function handleArguments(array $arguments): array
    {
        if ($this->hasArgument('--configuration', $arguments) || $this->hasCustomConfigurationFile()) {
            return $arguments;
        }

        $arguments = $this->pushArgument('--configuration', $arguments);

        return $this->pushArgument((string) realpath($this->fromGeneratedConfigurationFile()), $arguments);
    }

    /**
     * Get the configuration file from the generated configuration file.
     */
    private function fromGeneratedConfigurationFile(): string
    {
        $path = $this->getTempPhpunitXmlPath();
        if (file_exists($path)) {
            unlink($path);
        }

        $doc = new DOMDocument;
        $doc->load(self::BASE_PHPUNIT_FILE);

        $contents = $doc->saveXML();

        assert(is_int(file_put_contents($path, $contents)));

        return $path;
    }

    /**
     * Check if the configuration file is custom.
     */
    private function hasCustomConfigurationFile(): bool
    {
        $cliConfiguration = (new CliConfigurationBuilder)->fromParameters([]);
        $configurationFile = (new XmlConfigurationFileFinder)->find($cliConfiguration);

        return is_string($configurationFile);
    }

    /**
     * Get the temporary phpunit.xml path.
     */
    private function getTempPhpunitXmlPath(): string
    {
        return getcwd().'/.pest.xml';
    }

    /**
     * Terminates the plugin.
     */
    public function terminate(): void
    {
        $path = $this->getTempPhpunitXmlPath();

        if (file_exists($path)) {
            unlink($path);
        }
    }
}
