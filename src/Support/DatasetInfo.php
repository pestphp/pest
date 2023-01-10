<?php

declare(strict_types=1);

namespace Pest\Support;

/**
 * @internal
 */
final class DatasetInfo
{
    public const DATASETS_DIR_NAME = 'Datasets';

    public const DATASETS_FILE_NAME = 'Datasets.php';

    public static function isInsideADatasetsDirectory(string $file): bool
    {
        return basename(dirname($file)) === self::DATASETS_DIR_NAME;
    }

    public static function isADatasetsFile(string $file): bool
    {
        return basename($file) === self::DATASETS_FILE_NAME;
    }

    public static function scope(string $file): string
    {
        if (self::isInsideADatasetsDirectory($file)) {
            return dirname($file, 2);
        }

        if (self::isADatasetsFile($file)) {
            return dirname($file);
        }

        return $file;
    }
}
