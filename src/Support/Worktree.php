<?php

declare(strict_types=1);

namespace Pest\Support;

/**
 * @internal
 */
final class Worktree
{
    /**
     * Resolve the project root path, accounting for git worktrees
     * where vendor is a symlink to another worktree's vendor directory.
     *
     * When the current working directory has a `vendor` symlink that
     * resolves to the same directory as the autoloaded vendor path,
     * this means we're inside a git worktree whose vendor directory
     * is symlinked to the main worktree. In that case, the project
     * root should be the current working directory (the worktree),
     * so that the worktree's own `Pest.php`, `tests/`, and
     * `phpunit.xml` are found.
     */
    public static function resolveRoot(string $autoloadPath): string
    {
        $rootPath = dirname($autoloadPath, 2);

        $cwd = getcwd();
        if ($cwd !== false
            && is_link($cwd.'/vendor')
            && realpath($cwd.'/vendor') === dirname($autoloadPath, 1)) {
            $rootPath = $cwd;
        }

        $_ENV['APP_BASE_PATH'] = $rootPath;

        return $rootPath;
    }
}
