<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * @internal
 */
final class GitRepository
{
    public static function locate(string $path): ?string
    {
        $dir = $path;

        while (true) {
            $dotGit = $dir.DIRECTORY_SEPARATOR.'.git';

            if (is_dir($dotGit) || is_file($dotGit)) {
                return $dotGit;
            }

            $parent = dirname($dir);

            if ($parent === $dir) {
                return null;
            }

            $dir = $parent;
        }
    }

    public static function configPath(string $path): ?string
    {
        $dotGit = self::locate($path);

        if ($dotGit === null || ! is_dir($dotGit)) {
            return null;
        }

        $config = $dotGit.DIRECTORY_SEPARATOR.'config';

        return is_file($config) ? $config : null;
    }

    public static function subdirectoryPrefix(string $projectRoot): string
    {
        $dotGit = self::locate($projectRoot);

        if ($dotGit === null) {
            return '';
        }

        $repoRoot = dirname($dotGit);
        $realRepoRoot = @realpath($repoRoot);
        $realProjectRoot = @realpath($projectRoot);

        if ($realRepoRoot === false || $realProjectRoot === false || $realRepoRoot === $realProjectRoot) {
            return '';
        }

        $prefix = substr($realProjectRoot, strlen(rtrim($realRepoRoot, DIRECTORY_SEPARATOR)) + 1);

        return str_replace(DIRECTORY_SEPARATOR, '/', rtrim($prefix, DIRECTORY_SEPARATOR)).'/';
    }
}
