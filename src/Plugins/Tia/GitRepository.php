<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

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

        if ($dotGit === null) {
            return null;
        }

        $gitDir = is_dir($dotGit) ? $dotGit : self::resolveGitDirFile($dotGit);

        if ($gitDir === null) {
            return null;
        }

        $config = $gitDir.DIRECTORY_SEPARATOR.'config';

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

    private static function resolveGitDirFile(string $dotGitFile): ?string
    {
        $content = @file_get_contents($dotGitFile);

        if ($content === false || preg_match('/^gitdir:\s*(.+)$/m', $content, $match) !== 1) {
            return null;
        }

        $gitDir = trim($match[1]);

        if (! self::isAbsolutePath($gitDir)) {
            $gitDir = dirname($dotGitFile).DIRECTORY_SEPARATOR.$gitDir;
        }

        $commonDir = $gitDir.DIRECTORY_SEPARATOR.'commondir';

        if (is_file($commonDir)) {
            $common = trim((string) @file_get_contents($commonDir));

            if ($common !== '') {
                $gitDir = self::isAbsolutePath($common)
                    ? $common
                    : $gitDir.DIRECTORY_SEPARATOR.$common;
            }
        }

        $resolved = @realpath($gitDir);

        return $resolved === false ? null : $resolved;
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }
}
