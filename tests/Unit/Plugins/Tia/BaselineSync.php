<?php

declare(strict_types=1);

use Pest\Plugins\Tia\BaselineSync;
use Pest\Plugins\Tia\FileState;
use Pest\Support\Reflection;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Process\Process;

function baselineSyncGit(string $cwd, string ...$args): void
{
    $process = new Process(['git', ...$args], $cwd);
    $process->setTimeout(10.0);
    $process->mustRun();
}

function baselineSyncRepository(?string $origin): string
{
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest_baseline_sync_'.uniqid();
    mkdir($root, 0777, true);

    baselineSyncGit($root, 'init', '-q');
    baselineSyncGit($root, 'config', 'user.email', 'pest@example.com');
    baselineSyncGit($root, 'config', 'user.name', 'Pest');

    if ($origin !== null) {
        baselineSyncGit($root, 'remote', 'add', 'origin', $origin);
    }

    return $root;
}

function baselineSyncRemoveDirectory(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $file) {
        @chmod($file->getPathname(), 0777);
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }

    @rmdir($path);
}

function baselineSyncDetect(string $projectRoot): ?string
{
    $sync = new BaselineSync(new FileState($projectRoot.DIRECTORY_SEPARATOR.'.pest-state'), new NullOutput);

    /** @var ?string $repo */
    $repo = Reflection::call($sync, 'detectGitHubRepo', [$projectRoot]);

    return $repo;
}

describe('detectGitHubRepo()', function (): void {
    it('detects the repository from an ssh origin in a regular clone', function (): void {
        $clone = baselineSyncRepository('git@github.com:foo/bar.git');

        try {
            expect(baselineSyncDetect($clone))->toBe('foo/bar');
        } finally {
            baselineSyncRemoveDirectory($clone);
        }
    });

    it('detects the repository from an https origin in a regular clone', function (): void {
        $clone = baselineSyncRepository('https://github.com/foo/bar.git');

        try {
            expect(baselineSyncDetect($clone))->toBe('foo/bar');
        } finally {
            baselineSyncRemoveDirectory($clone);
        }
    });

    it('detects the repository inside a linked git worktree', function (): void {
        $clone = baselineSyncRepository('git@github.com:foo/bar.git');
        $worktree = $clone.'-wt';

        try {
            file_put_contents($clone.'/README.md', 'pest');
            baselineSyncGit($clone, 'add', '-A');
            baselineSyncGit($clone, 'commit', '-q', '-m', 'init');
            baselineSyncGit($clone, 'worktree', 'add', '-q', $worktree);

            expect(baselineSyncDetect($worktree))->toBe('foo/bar');
        } finally {
            baselineSyncRemoveDirectory($worktree);
            baselineSyncRemoveDirectory($clone);
        }
    });

    it('returns null for a non-github origin', function (): void {
        $clone = baselineSyncRepository('git@gitlab.com:foo/bar.git');

        try {
            expect(baselineSyncDetect($clone))->toBeNull();
        } finally {
            baselineSyncRemoveDirectory($clone);
        }
    });

    it('returns null when there is no origin remote', function (): void {
        $clone = baselineSyncRepository(null);

        try {
            expect(baselineSyncDetect($clone))->toBeNull();
        } finally {
            baselineSyncRemoveDirectory($clone);
        }
    });
});
