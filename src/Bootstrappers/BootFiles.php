<?php

declare(strict_types=1);

namespace Pest\Bootstrappers;

use Pest\Support\Str;
use function Pest\testDirectory;
use Pest\TestSuite;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * @internal
 */
final class BootFiles
{
    /**
     * The Pest convention.
     *
     * @var array<int, string>
     */
    private const STRUCTURE = [
        'Datasets',
        'Datasets.php',
        'Expectations',
        'Expectations.php',
        'Helpers',
        'Helpers.php',
        'Pest.php',
    ];

    /**
     * Boots the Subscribers.
     */
    public function __invoke(): void
    {
        $rootPath = TestSuite::getInstance()->rootPath;
        $testsPath = $rootPath.DIRECTORY_SEPARATOR.testDirectory();

        foreach (self::STRUCTURE as $filename) {
            $filename = sprintf('%s%s%s', $testsPath, DIRECTORY_SEPARATOR, $filename);

            if (! file_exists($filename)) {
                continue;
            }

            if (is_dir($filename)) {
                $directory = new RecursiveDirectoryIterator($filename);
                $iterator = new RecursiveIteratorIterator($directory);
                /** @var \DirectoryIterator $file */
                foreach ($iterator as $file) {
                    $this->load($file->__toString());
                }
            } else {
                $this->load($filename);
            }
        }
    }

    /**
     * Loads, if possible, the given file.
     */
    private function load(string $filename): void
    {
        if (! Str::endsWith($filename, '.php')) {
            return;
        }

        if (! file_exists($filename)) {
            return;
        }

        include_once $filename;
    }
}
