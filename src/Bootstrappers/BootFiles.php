<?php

declare(strict_types=1);

namespace Pest\Bootstrappers;

use Pest\Contracts\Bootstrapper;
use Pest\Exceptions\FatalException;
use Pest\Support\DatasetInfo;
use Pest\Support\Str;
use Pest\TestSuite;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SebastianBergmann\FileIterator\Facade as PhpUnitFileIterator;

use function Pest\testDirectory;

/**
 * @internal
 */
final class BootFiles implements Bootstrapper
{
    /**
     * The structure of the tests directory.
     *
     * @var array<int, string>
     */
    private const array STRUCTURE = [
        'Expectations',
        'Expectations.php',
        'Helpers',
        'Helpers.php',
        'Pest.php',
    ];

    /**
     * Boots the structure of the tests directory.
     */
    public function boot(): void
    {
        $rootPath = TestSuite::getInstance()->rootPath;
        $testsPath = $rootPath.DIRECTORY_SEPARATOR.testDirectory();

        if (! is_dir($testsPath)) {
            throw new FatalException(sprintf('The test directory [%s] does not exist.', $testsPath));
        }

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

        $this->bootDatasets($testsPath);
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

    private function bootDatasets(string $testsPath): void
    {
        assert($testsPath !== '');

        $files = (new PhpUnitFileIterator)->getFilesAsArray($testsPath, '.php');

        foreach ($files as $file) {
            if (DatasetInfo::isADatasetsFile($file) || DatasetInfo::isInsideADatasetsDirectory($file)) {
                $this->load($file);
            }
        }
    }
}
