<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * @internal
 */
final readonly class AutoloadEdges
{
    /**
     * @return array<string, true>
     */
    public static function snapshot(): array
    {
        $files = [];

        foreach (get_included_files() as $file) {
            if (is_string($file) && $file !== '') {
                $files[$file] = true;
            }
        }

        return $files;
    }

    /**
     * @param  array<string, true>  $before
     * @param  array<string, true>  $after
     * @return list<string>
     */
    public static function newProjectFiles(array $before, array $after, string $projectRoot, ?string $testFile = null): array
    {
        $root = rtrim($projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $testReal = is_string($testFile) && $testFile !== '' ? @realpath($testFile) : false;
        $out = [];

        foreach (array_keys($after) as $file) {
            if (isset($before[$file])) {
                continue;
            }

            $real = @realpath($file);
            if ($real === false) {
                $real = $file;
            }

            if ($testReal !== false && $real === $testReal) {
                continue;
            }

            if (! str_starts_with($real, $root)) {
                continue;
            }

            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen($root)));

            if (self::ignored($relative)) {
                continue;
            }

            if (! str_ends_with($relative, '.php')) {
                continue;
            }

            $out[$real] = true;
        }

        return array_keys($out);
    }

    private static function ignored(string $relative): bool
    {
        static $prefixes = [
            'vendor/',
            'node_modules/',
            'storage/framework/',
            'bootstrap/cache/',
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
