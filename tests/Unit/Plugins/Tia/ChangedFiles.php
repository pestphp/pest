<?php

declare(strict_types=1);

use Pest\Plugins\Tia\ChangedFiles;
use Symfony\Component\Process\Process;

function tia_changed_files_run(array $command, string $cwd): void
{
    $process = new Process($command, $cwd);
    $process->mustRun();
}

/**
 * Creates a git repository containing a `backend/` Pest project and a
 * `frontend/` sibling package, with one initial commit.
 *
 * @return array{root: string, sha: string}
 */
function tia_changed_files_monorepo(): array
{
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest_tia_monorepo_'.uniqid();

    mkdir($root.'/backend/app', 0o777, true);
    mkdir($root.'/frontend', 0o777, true);

    file_put_contents($root.'/backend/app/Service.php', "<?php\n\$a = 1;\n");
    file_put_contents($root.'/backend/composer.lock', '{"packages": []}');
    file_put_contents($root.'/frontend/widget.php', "<?php\n\$w = 1;\n");

    tia_changed_files_run(['git', 'init', '-q', '-b', 'main'], $root);
    tia_changed_files_run(['git', 'config', 'user.email', 'tia@pestphp.com'], $root);
    tia_changed_files_run(['git', 'config', 'user.name', 'Tia'], $root);
    tia_changed_files_run(['git', 'add', '-A'], $root);
    tia_changed_files_run(['git', 'commit', '-q', '-m', 'initial'], $root);

    $process = new Process(['git', 'rev-parse', 'HEAD'], $root);
    $process->mustRun();

    return ['root' => $root, 'sha' => trim($process->getOutput())];
}

function tia_changed_files_rm(string $directory): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($directory);
}

describe('gitPrefix()', function (): void {
    it('is empty at the repository root', function (): void {
        $fixture = tia_changed_files_monorepo();

        try {
            expect(new ChangedFiles($fixture['root'])->gitPrefix())->toBeEmpty();
        } finally {
            tia_changed_files_rm($fixture['root']);
        }
    });

    it('is the slash-terminated subdirectory path inside a larger repository', function (): void {
        $fixture = tia_changed_files_monorepo();

        try {
            expect(new ChangedFiles($fixture['root'].'/backend')->gitPrefix())->toBe('backend/');
        } finally {
            tia_changed_files_rm($fixture['root']);
        }
    });

    it('is empty outside any git repository', function (): void {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest_tia_no_repo_'.uniqid();
        mkdir($directory);

        try {
            expect(new ChangedFiles($directory)->gitPrefix())->toBeEmpty();
        } finally {
            tia_changed_files_rm($directory);
        }
    });
});

describe('a project in a repository subdirectory', function (): void {
    it('reports working-tree changes as project-relative paths and drops sibling packages', function (): void {
        $fixture = tia_changed_files_monorepo();

        try {
            file_put_contents($fixture['root'].'/backend/app/Service.php', "<?php\n\$a = 2;\n");
            file_put_contents($fixture['root'].'/backend/app/Untracked.php', "<?php\n\$u = 1;\n");
            file_put_contents($fixture['root'].'/frontend/widget.php', "<?php\n\$w = 2;\n");

            $changed = new ChangedFiles($fixture['root'].'/backend')->since(null);

            sort($changed);

            expect($changed)->toBe(['app/Service.php', 'app/Untracked.php']);
        } finally {
            tia_changed_files_rm($fixture['root']);
        }
    });

    it('reports committed changes since a sha as project-relative paths', function (): void {
        $fixture = tia_changed_files_monorepo();

        try {
            file_put_contents($fixture['root'].'/backend/app/Service.php', "<?php\n\$a = 2;\n");
            file_put_contents($fixture['root'].'/frontend/widget.php', "<?php\n\$w = 2;\n");
            tia_changed_files_run(['git', 'commit', '-q', '-am', 'change both packages'], $fixture['root']);

            expect(new ChangedFiles($fixture['root'].'/backend')->since($fixture['sha']))
                ->toBe(['app/Service.php']);
        } finally {
            tia_changed_files_rm($fixture['root']);
        }
    });

    it('filters files whose content is behaviourally unchanged against the baseline sha', function (): void {
        $fixture = tia_changed_files_monorepo();

        try {
            file_put_contents($fixture['root'].'/backend/app/Service.php', "<?php\n\$a = 2;\n");
            tia_changed_files_run(['git', 'commit', '-q', '-am', 'change service'], $fixture['root']);

            // Reverting the content makes the file diff against the baseline
            // sha while hashing identically to it — reachable only when
            // `git show` receives the repository-relative path.
            file_put_contents($fixture['root'].'/backend/app/Service.php', "<?php\n\$a = 1;\n");

            expect(new ChangedFiles($fixture['root'].'/backend')->since($fixture['sha']))->toBe([]);
        } finally {
            tia_changed_files_rm($fixture['root']);
        }
    });
});

describe('a project at the repository root', function (): void {
    it('still reports repository-relative paths untouched', function (): void {
        $fixture = tia_changed_files_monorepo();

        try {
            file_put_contents($fixture['root'].'/backend/app/Service.php', "<?php\n\$a = 2;\n");

            expect(new ChangedFiles($fixture['root'])->since(null))->toBe(['backend/app/Service.php']);
        } finally {
            tia_changed_files_rm($fixture['root']);
        }
    });
});
