<?php

declare(strict_types=1);

namespace Pest\Support;

use function Pest\testDirectory;

/**
 * @internal
 */
final class DatasetInfo
{
    public const string DATASETS_DIR_NAME = 'Datasets';

    public const string DATASETS_FILE_NAME = 'Datasets.php';

    public static function isInsideADatasetsDirectory(string $file): bool
    {
        return in_array(self::DATASETS_DIR_NAME, self::directorySegmentsInsideTestsDirectory($file), true);
    }

    public static function isADatasetsFile(string $file): bool
    {
        return basename($file) === self::DATASETS_FILE_NAME;
    }

    public static function scope(string $file): string
    {
        if (Str::endsWith($file, testDirectory('Pest.php'))) {
            return dirname($file);
        }

        if (self::isInsideADatasetsDirectory($file)) {
            $scope = [];

            foreach (self::directorySegmentsInsideTestsDirectory($file) as $segment) {
                if ($segment === self::DATASETS_DIR_NAME) {
                    break;
                }

                $scope[] = $segment;
            }

            $testsDirectoryPath = self::testsDirectoryPath($file);

            if ($scope === []) {
                return $testsDirectoryPath;
            }

            return $testsDirectoryPath.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $scope);
        }

        if (self::isADatasetsFile($file)) {
            return dirname($file);
        }

        return $file;
    }

    /**
     * @return list<string>
     */
    private static function directorySegmentsInsideTestsDirectory(string $file): array
    {
        $directory = dirname(self::pathInsideTestsDirectory($file));

        if ($directory === '.' || $directory === DIRECTORY_SEPARATOR) {
            return [];
        }

        return array_values(array_filter(
            explode(DIRECTORY_SEPARATOR, trim($directory, DIRECTORY_SEPARATOR)),
            static fn (string $segment): bool => $segment !== '',
        ));
    }

    private static function pathInsideTestsDirectory(string $file): string
    {
        $testsDirectory = DIRECTORY_SEPARATOR.trim(testDirectory(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $position = strrpos($file, $testsDirectory);

        if ($position === false) {
            return $file;
        }

        return substr($file, $position + strlen($testsDirectory));
    }

    private static function testsDirectoryPath(string $file): string
    {
        $testsDirectory = DIRECTORY_SEPARATOR.trim(testDirectory(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $position = strrpos($file, $testsDirectory);

        if ($position === false) {
            return dirname($file);
        }

        return substr($file, 0, $position + strlen($testsDirectory) - 1);
    }
}
