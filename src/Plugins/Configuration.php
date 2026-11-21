<?php

declare(strict_types=1);

namespace Pest\Plugins;

use DOMDocument;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Contracts\Plugins\Terminable;
use Pest\Plugins\Concerns\HandleArguments;
use PHPUnit\Event\Facade as EventFacade;
use PHPUnit\TextUI\CliArguments\Builder as CliConfigurationBuilder;
use PHPUnit\TextUI\CliArguments\XmlConfigurationFileFinder;
use RuntimeException;

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

    /** @var array{path: string, dev: int, ino: int}|null */
    private ?array $generatedConfiguration = null;

    public function handleArguments(array $arguments): array
    {
        if ($this->hasArgument('--configuration', $arguments) || $this->hasArgument('-c', $arguments) || $this->hasCustomConfigurationFile()) {
            return $arguments;
        }

        $arguments = $this->pushArgument('--configuration', $arguments);

        return $this->pushArgument((string) realpath($this->fromGeneratedConfigurationFile()), $arguments);
    }

    public function generatedConfigurationPath(): ?string
    {
        return $this->generatedConfiguration['path'] ?? null;
    }

    private function fromGeneratedConfigurationFile(): string
    {
        $directory = getcwd();

        if ($directory === false) {
            throw new RuntimeException('Unable to determine the working directory.');
        }

        $doc = new DOMDocument;
        $doc->load(self::BASE_PHPUNIT_FILE);

        $contents = $doc->saveXML();
        assert(is_string($contents));

        foreach (['.pest.xml', '.pest-fallback.xml', null] as $filename) {
            $filename ??= '.pest-'.bin2hex(random_bytes(8)).'.xml';
            $path = $directory.DIRECTORY_SEPARATOR.$filename;
            $file = @fopen($path, 'xb');

            if ($file === false) {
                continue;
            }

            try {
                $stat = @fstat($file);

                if ($stat === false) {
                    throw new RuntimeException('Unable to identify the temporary PHPUnit configuration file.');
                }

                $this->generatedConfiguration = ['path' => $path, 'dev' => $stat['dev'], 'ino' => $stat['ino']];
                $written = @fwrite($file, $contents);
            } finally {
                fclose($file);
            }

            if ($written !== strlen($contents)) {
                $this->terminate();

                throw new RuntimeException('Unable to write the temporary PHPUnit configuration file.');
            }

            return $path;
        }

        throw new RuntimeException('Unable to create a temporary PHPUnit configuration file.');
    }

    private function hasCustomConfigurationFile(): bool
    {
        $cliConfiguration = new CliConfigurationBuilder(EventFacade::emitter())->fromParameters([]);
        $configurationFile = (new XmlConfigurationFileFinder)->find($cliConfiguration);

        return is_string($configurationFile);
    }

    public function terminate(): void
    {
        $configuration = $this->generatedConfiguration;
        $this->generatedConfiguration = null;

        if ($configuration === null) {
            return;
        }

        clearstatcache();
        $stat = @lstat($configuration['path']);

        if ($stat !== false && $stat['dev'] === $configuration['dev'] && $stat['ino'] === $configuration['ino']) {
            unlink($configuration['path']);
        }
    }
}
