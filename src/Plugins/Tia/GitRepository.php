<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * Locates the git repository governing a path, which for projects living in
 * a subdirectory of a monorepo sits in an ancestor directory rather than the
 * project root itself. Paths are walked as strings, so callers pass
 * canonical (realpath'd) roots.
 *
 * @internal
 */
final class GitRepository
{
    /**
     * The nearest ancestor `.git` entry governing the given path — a
     * directory for regular repositories, a file for worktrees and
     * submodules — or `null` when the path is not inside a repository.
     */
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

    /**
     * The governing repository's config file, or `null` when there is none
     * or it cannot be resolved by static inspection.
     */
    public static function configPath(string $path): ?string
    {
        $dotGit = self::locate($path);

        if ($dotGit === null || ! is_dir($dotGit)) {
            return null;
        }

        $config = $dotGit.DIRECTORY_SEPARATOR.'config';

        return is_file($config) ? $config : null;
    }

    /**
     * The project's path relative to the governing repository's root, with a
     * trailing slash (`apps/api/`), or an empty string when the project is
     * the repository root or sits outside any repository.
     */
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
