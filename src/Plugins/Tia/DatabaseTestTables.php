<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * @internal
 */
final class DatabaseTestTables
{
    /**
     * @param  array<string, array<int, string>>  $perTestTables
     * @param  array<string, true>  $perTestUsesDatabase
     * @return array<string, array<int, string>>
     */
    public static function augment(array $perTestTables, array $perTestUsesDatabase, string $projectRoot): array
    {
        $untracked = array_filter(
            array_keys($perTestUsesDatabase),
            fn (string $testFile): bool => ($perTestTables[$testFile] ?? []) === [],
        );

        if ($untracked === []) {
            return $perTestTables;
        }

        $allTables = self::declaredTables($projectRoot);

        if ($allTables === []) {
            return $perTestTables;
        }

        foreach ($untracked as $testFile) {
            $perTestTables[$testFile] = $allTables;
        }

        return $perTestTables;
    }

    /**
     * @return array<int, string>
     */
    private static function declaredTables(string $projectRoot): array
    {
        $migrationDir = rtrim($projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';

        if (! is_dir($migrationDir)) {
            return [];
        }

        $tables = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($migrationDir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile()) {
                continue;
            }

            if (! str_ends_with(strtolower((string) $fileInfo->getPathname()), '.php')) {
                continue;
            }

            $content = @file_get_contents((string) $fileInfo->getPathname());

            if ($content === false) {
                continue;
            }

            foreach (TableExtractor::fromMigrationSource($content) as $table) {
                $tables[strtolower($table)] = true;
            }
        }

        $names = array_keys($tables);
        sort($names);

        return $names;
    }
}
