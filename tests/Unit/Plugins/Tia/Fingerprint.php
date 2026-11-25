<?php

declare(strict_types=1);

use Pest\Plugins\Tia\ConfigurationFile;
use Pest\Plugins\Tia\ExternalSources;
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
                ->and($fingerprint['structural']['phpunit_configuration'])->not->toBeNull();
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
                ->and($worktreeFingerprint['structural']['phpunit_configuration'])->not->toBeNull()
                ->and($worktreeFingerprint['structural']['composer_lock'])->toBe($cloneFingerprint['structural']['composer_lock'])
                ->and($worktreeFingerprint['structural']['phpunit_configuration'])->toBe($cloneFingerprint['structural']['phpunit_configuration']);
        } finally {
            fingerprintRemoveDirectory($clone);
        }
    });

    it('excludes gitignored untracked files from the fingerprint', function (): void {
        $clone = fingerprintRepository();

        try {
            file_put_contents($clone.'/phpunit.xml', '<phpunit/>');
            file_put_contents($clone.'/.gitignore', "composer.lock\n");
            fingerprintGit($clone, 'add', '-A');
            fingerprintGit($clone, 'commit', '-q', '-m', 'init');

            file_put_contents($clone.'/composer.lock', '{"packages": []}');

            $fingerprint = Fingerprint::compute($clone);

            expect($fingerprint['structural']['composer_lock'])->toBeNull()
                ->and($fingerprint['structural']['phpunit_configuration'])->not->toBeNull();
        } finally {
            fingerprintRemoveDirectory($clone);
        }
    });

    it('keeps the command line bootstrap and include path out of a run that sets neither', function (): void {
        $root = fingerprintRepository();

        try {
            $structural = Fingerprint::compute($root, ConfigurationFile::fromArguments(['--tia']))['structural'];

            expect($structural)->not->toHaveKey('phpunit_bootstrap')
                ->and($structural)->not->toHaveKey('phpunit_include_path');
        } finally {
            fingerprintRemoveDirectory($root);
        }
    });

    it('tells a bootstrap whose name looks like an include path apart from a bootstrap and an include path', function (): void {
        $root = fingerprintRepository();

        try {
            $bootstrapOnly = Fingerprint::compute($root, ConfigurationFile::fromArguments(['--bootstrap', 'bootstrap.php;include-path=app']));
            $bootstrapAndIncludePath = Fingerprint::compute($root, ConfigurationFile::fromArguments(['--bootstrap', 'bootstrap.php', '--include-path', 'app']));

            expect(Fingerprint::structuralMatches($bootstrapOnly, $bootstrapAndIncludePath))->toBeFalse()
                ->and(Fingerprint::structuralDrift($bootstrapOnly, $bootstrapAndIncludePath))->toBe(['phpunit_bootstrap', 'phpunit_include_path']);
        } finally {
            fingerprintRemoveDirectory($root);
        }
    });

    it('tells a relative include path from the absolute path it names', function (): void {
        $root = (string) realpath(fingerprintRepository());
        $previous = getcwd();
        chdir($root);

        try {
            $relativeFirst = Fingerprint::compute($root, null, ['--include-path', '.'.PATH_SEPARATOR.$root]);
            $absoluteFirst = Fingerprint::compute($root, null, ['--include-path', $root.PATH_SEPARATOR.'.']);

            expect(Fingerprint::structuralMatches($relativeFirst, $absoluteFirst))->toBeFalse();
        } finally {
            chdir((string) $previous);
            fingerprintRemoveDirectory($root);
        }
    });

    it('stores an include path whose name is not utf-8 as text json can encode', function (): void {
        $root = (string) realpath(fingerprintRepository());
        mkdir($root.'/app');
        file_put_contents($root.'/app/composer.json', '{}');
        fingerprintGit($root, 'add', '-A');
        fingerprintGit($root, 'commit', '-q', '-m', 'init');
        $previous = getcwd();
        chdir($root.'/app');
        ExternalSources::flush();

        try {
            $fingerprint = Fingerprint::compute($root.'/app', null, ['--include-path', "../optional-\xff"]);

            expect($fingerprint['structural'])->toHaveKeys(['external_roots', 'include_path'])
                ->and(json_encode($fingerprint))->toBeString();
        } finally {
            chdir((string) $previous);
            ExternalSources::flush();
            fingerprintRemoveDirectory($root);
        }
    });

    it('changes when the order of the include path changes and not when its spelling does', function (): void {
        $root = (string) realpath(fingerprintRepository());
        mkdir($root.'/a');
        mkdir($root.'/b');

        try {
            $forward = Fingerprint::compute($root, null, ['--include-path', $root.'/a'.PATH_SEPARATOR.$root.'/b']);
            $respelled = Fingerprint::compute($root, null, ['--include-path', $root.'/./a/'.PATH_SEPARATOR.$root.'/b']);
            $reversed = Fingerprint::compute($root, null, ['--include-path', $root.'/b'.PATH_SEPARATOR.$root.'/a']);

            expect(Fingerprint::structuralMatches($forward, $respelled))->toBeTrue()
                ->and(Fingerprint::structuralMatches($forward, $reversed))->toBeFalse();
        } finally {
            fingerprintRemoveDirectory($root);
        }
    });
});
