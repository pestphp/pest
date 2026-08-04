<?php

declare(strict_types=1);

use Pest\Plugins\Tia\Bootstrapper;
use Pest\Plugins\Tia\Configuration;
use Pest\Plugins\Tia\Contracts\State;
use Pest\Plugins\Tia\FileState;
use Pest\Plugins\Tia\Storage;
use Pest\Support\Container;
use Pest\TestSuite;
use Symfony\Component\Process\Process;

afterEach(function (): void {
    Storage::useDirectory(null);
});

it('uses a project-relative configured directory', function (): void {
    $container = new Container;
    $testSuite = new TestSuite(sys_get_temp_dir(), 'tests');
    $container->add(TestSuite::class, $testSuite);

    (new Configuration)->directory('.pest/tia');
    new Bootstrapper($container)->boot();

    $state = $container->get(State::class);

    if (! $state instanceof FileState) {
        throw new RuntimeException('Expected the TIA state to use file storage.');
    }

    expect(Storage::tempDir('/project'))->toBe('/project'.DIRECTORY_SEPARATOR.'.pest/tia')
        ->and($state->pathFor('graph.json'))->toBe($testSuite->rootPath.DIRECTORY_SEPARATOR.'.pest/tia/graph.json');
});

it('uses an absolute configured directory', function (): void {
    (new Configuration)->directory('/tmp/pest-tia');

    expect(Storage::tempDir('/project'))->toBe('/tmp/pest-tia');
});

function storageGit(string $cwd, string ...$args): void
{
    $process = new Process(['git', ...$args], $cwd);
    $process->setTimeout(10.0);
    $process->mustRun();
}

function storageRepository(?string $origin): string
{
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest_storage_'.uniqid();
    mkdir($root, 0777, true);

    storageGit($root, 'init', '-q');
    storageGit($root, 'config', 'user.email', 'pest@example.com');
    storageGit($root, 'config', 'user.name', 'Pest');

    if ($origin !== null) {
        storageGit($root, 'remote', 'add', 'origin', $origin);
    }

    return $root;
}

function storageRemoveDirectory(string $path): void
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

function storageKeyHash(string $projectRoot): string
{
    preg_match('/([a-f0-9]{16})$/', basename(Storage::tempDir($projectRoot)), $match);

    return $match[1] ?? '';
}

describe('tempDir()', function (): void {
    it('derives the storage key from the origin remote in a regular clone', function (): void {
        $clone = storageRepository('git@github.com:foo/bar.git');

        try {
            expect(storageKeyHash($clone))->toBe(substr(hash('sha256', 'github.com/foo/bar'), 0, 16));
        } finally {
            storageRemoveDirectory($clone);
        }
    });

    it('derives the same origin key inside a linked git worktree', function (): void {
        $clone = storageRepository('git@github.com:foo/bar.git');
        $worktree = $clone.'-wt';

        try {
            file_put_contents($clone.'/README.md', 'pest');
            storageGit($clone, 'add', '-A');
            storageGit($clone, 'commit', '-q', '-m', 'init');
            storageGit($clone, 'worktree', 'add', '-q', $worktree);

            expect(storageKeyHash($worktree))->toBe(storageKeyHash($clone))
                ->and(storageKeyHash($worktree))->toBe(substr(hash('sha256', 'github.com/foo/bar'), 0, 16));
        } finally {
            storageRemoveDirectory($worktree);
            storageRemoveDirectory($clone);
        }
    });

    it('falls back to a path-derived key when there is no origin remote', function (): void {
        $clone = storageRepository(null);

        try {
            $realpath = realpath($clone);

            expect($realpath)->not->toBeFalse()
                ->and(storageKeyHash($clone))->toBe(substr(hash('sha256', (string) $realpath), 0, 16));
        } finally {
            storageRemoveDirectory($clone);
        }
    });
});
