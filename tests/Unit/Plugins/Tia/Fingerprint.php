<?php

declare(strict_types=1);

use Pest\Plugins\Tia\Fingerprint;
use Symfony\Component\Process\Process;

function fingerprintGit(string $cwd, string ...$args): void
{
    $process = new Process(['git', ...$args], $cwd);
    $process->setTimeout(10.0);
    $process->mustRun();
}

function fingerprintRepository(): string
{
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest_fingerprint_'.uniqid();
    mkdir($root, 0777, true);

    fingerprintGit($root, 'init', '-q');
    fingerprintGit($root, 'config', 'user.email', 'pest@example.com');
    fingerprintGit($root, 'config', 'user.name', 'Pest');

    return $root;
}

function fingerprintRemoveDirectory(string $path): void
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

describe('compute()', function (): void {
    it('fingerprints tracked structural files in a regular clone', function (): void {
        $clone = fingerprintRepository();

        try {
            file_put_contents($clone.'/composer.lock', '{"packages": []}');
            file_put_contents($clone.'/phpunit.xml', '<phpunit/>');
            fingerprintGit($clone, 'add', '-A');
            fingerprintGit($clone, 'commit', '-q', '-m', 'init');

            $fingerprint = Fingerprint::compute($clone);

            expect($fingerprint['structural']['composer_lock'])->not->toBeNull()
                ->and($fingerprint['structural']['phpunit_xml'])->not->toBeNull();
        } finally {
            fingerprintRemoveDirectory($clone);
        }
    });

    it('fingerprints tracked structural files inside a linked git worktree', function (): void {
        $clone = fingerprintRepository();

        try {
            file_put_contents($clone.'/composer.lock', '{"packages": []}');
            file_put_contents($clone.'/phpunit.xml', '<phpunit/>');
            file_put_contents($clone.'/.gitignore', ".worktrees/\n");
            fingerprintGit($clone, 'add', '-A');
            fingerprintGit($clone, 'commit', '-q', '-m', 'init');
            fingerprintGit($clone, 'worktree', 'add', '-q', '.worktrees/feature');

            $worktree = $clone.'/.worktrees/feature';

            $cloneFingerprint = Fingerprint::compute($clone);
            $worktreeFingerprint = Fingerprint::compute($worktree);

            expect($worktreeFingerprint['structural']['composer_lock'])->not->toBeNull()
                ->and($worktreeFingerprint['structural']['phpunit_xml'])->not->toBeNull()
                ->and($worktreeFingerprint['structural']['composer_lock'])->toBe($cloneFingerprint['structural']['composer_lock'])
                ->and($worktreeFingerprint['structural']['phpunit_xml'])->toBe($cloneFingerprint['structural']['phpunit_xml']);
        } finally {
            fingerprintRemoveDirectory($clone);
        }
    });

    it('excludes gitignored untracked files from the fingerprint', function (): void {
        $clone = fingerprintRepository();

        try {
            file_put_contents($clone.'/composer.lock', '{"packages": []}');
            file_put_contents($clone.'/.gitignore', "phpunit.xml\n");
            fingerprintGit($clone, 'add', '-A');
            fingerprintGit($clone, 'commit', '-q', '-m', 'init');

            file_put_contents($clone.'/phpunit.xml', '<phpunit/>');

            $fingerprint = Fingerprint::compute($clone);

            expect($fingerprint['structural']['phpunit_xml'])->toBeNull()
                ->and($fingerprint['structural']['composer_lock'])->not->toBeNull();
        } finally {
            fingerprintRemoveDirectory($clone);
        }
    });
});
