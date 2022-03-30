<?php

declare(strict_types=1);

namespace Pest;

use Pest\Support\Str;
use SimpleXMLElement;
use Throwable;

/**
 * @internal
 */
final class ConfigLoader
{
    private ?SimpleXMLElement $config = null;

    /**
     * Default path if config loading went wrong.
     *
     * @var string
     */
    private const DEFAULT_TESTS_PATH = 'tests';

    /**
     * Creates a new instance of the config loader.
     */
    public function __construct(private string $rootPath)
    {
        $this->loadConfiguration();
    }

    /**
     * Get the tests directory or fallback to default path.
     */
    public function getTestsDirectory(): string
    {
        if (is_null($this->config)) {
            return self::DEFAULT_TESTS_PATH;
        }

        $suiteDirectory = $this->config->xpath('/phpunit/testsuites/testsuite/directory');

        // @phpstan-ignore-next-line
        if (!$suiteDirectory || count($suiteDirectory) === 0) {
            return self::DEFAULT_TESTS_PATH;
        }

        $directory = (string) ($suiteDirectory[0] ?? '');

        if ($directory === '') {
            return self::DEFAULT_TESTS_PATH;
        }

        // Return the whole directory if only a separator found (e.g. `./tests`)
        if (substr_count($directory, DIRECTORY_SEPARATOR) === 1) {
            return is_dir($directory) ? $directory : self::DEFAULT_TESTS_PATH;
        }

        $basePath = Str::beforeLast($directory, DIRECTORY_SEPARATOR);

        return is_dir($basePath) ? $basePath : self::DEFAULT_TESTS_PATH;
    }

    /**
     * Load the configuration file.
     */
    private function loadConfiguration(): void
    {
        $configPath = $this->getConfigurationFilePath();

        if ($configPath === false) {
            return;
        }

        $oldReportingLevel = error_reporting(0);
        $content           = file_get_contents($configPath);

        if ($content !== false) {
            try {
                $this->config = new SimpleXMLElement($content);
            } catch (Throwable) { // @phpstan-ignore-line
                // @ignoreException
            }
        }

        // Restore the correct error reporting
        error_reporting($oldReportingLevel);
    }

    /**
     * Get the configuration file path.
     */
    private function getConfigurationFilePath(): string|false
    {
        $candidates = [
            $this->rootPath . '/phpunit.xml',
            $this->rootPath . '/phpunit.dist.xml',
            $this->rootPath . '/phpunit.xml.dist',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return realpath($candidate);
            }
        }

        return false;
    }
}
