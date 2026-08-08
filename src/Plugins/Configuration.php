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

    public const string BASE_PHPUNIT_FILE = __DIR__
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'resources/base-phpunit.xml';

    public function handleArguments(array $arguments): array
    {
        if ($this->hasArgument('--configuration', $arguments) || $this->hasArgument('-c', $arguments) || $this->hasCustomConfigurationFile()) {
            return $arguments;
        }

        $arguments = $this->pushArgument('--configuration', $arguments);

        return $this->pushArgument((string) realpath($this->fromGeneratedConfigurationFile()), $arguments);
    }

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

    private function hasCustomConfigurationFile(): bool
    {
        $cliConfiguration = (new CliConfigurationBuilder)->fromParameters([]);
        $configurationFile = (new XmlConfigurationFileFinder)->find($cliConfiguration);

        return is_string($configurationFile);
    }

    private function getTempPhpunitXmlPath(): string
    {
        return getcwd().'/.pest.xml';
    }

    public function terminate(): void
    {
        $path = $this->getTempPhpunitXmlPath();

        if (file_exists($path)) {
            unlink($path);
        }
    }
}
