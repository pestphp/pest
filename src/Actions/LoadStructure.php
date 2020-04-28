<?php

declare(strict_types=1);

namespace Pest\Actions;

use Pest\Support\Str;
use PHPUnit\TextUI\Configuration\Configuration;
use PHPUnit\Util\FileLoader;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * @internal
 */
final class LoadStructure
{
    /**
     * The Pest convention.
     *
     * @var array<int, string>
     */
    private const STRUCTURE = [
        'Datasets.php',
        'Pest.php',
        'Datasets',
    ];

    /**
     * Validates the configuration in the given `configuration`.
     */
    public static function in(string $rootPath): void
    {
        $testsPath = $rootPath . DIRECTORY_SEPARATOR . 'tests';

        $load = function ($filename): bool {
            return file_exists($filename) && (bool) FileLoader::checkAndLoad($filename);
        };

        foreach (self::STRUCTURE as $filename) {
            $filename = sprintf('%s%s%s', $testsPath, DIRECTORY_SEPARATOR, $filename);

            if (!file_exists($filename)) {
                continue;
            }

            if (is_dir($filename)) {
                $directory = new RecursiveDirectoryIterator($filename);
                $iterator  = new RecursiveIteratorIterator($directory);
                foreach ($iterator as $file) {
                    $filename = $file->__toString();
                    if (Str::endsWith($filename, '.php') && file_exists($filename)) {
                        require_once $filename;
                    }
                }
            } else {
                $load($filename);
            }
        }
    }
}
