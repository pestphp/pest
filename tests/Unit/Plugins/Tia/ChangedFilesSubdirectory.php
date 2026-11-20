<?php

declare(strict_types=1);

use Pest\Plugins\Tia\ChangedFiles;
use Pest\Support\Git;
use Symfony\Component\Process\Process;

function subdirectoryGit(string $cwd, string ...$args): string
{
    $process = new Process(['git', ...$args], $cwd);
    $process->setTimeout(10.0);
    $process->mustRun();

    return $process->getOutput();
}

function subdirectoryWrite(string $root, string $path, string $contents): void
{
    $absolute = $root.'/'.$path;

    if (! is_dir(dirname($absolute))) {
        mkdir(dirname($absolute), 0777, true);
    }

    file_put_contents($absolute, $contents);
}

function subdirectoryRemove(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($path);
}

/**
 * @return array{root: string, project: string, sha: string}
 */
function subdirectoryRepository(): array
{
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest_tia_subdir_'.uniqid();
    mkdir($root, 0777, true);

    subdirectoryGit($root, 'init', '-q');
    subdirectoryGit($root, 'config', 'user.email', 'pest@example.com');
    subdirectoryGit($root, 'config', 'user.name', 'Pest');

    subdirectoryWrite($root, '.gitignore', "ignored.log\n");
    subdirectoryWrite($root, 'app/backend/src/a.php', "<?php\nreturn 1;\n");
    subdirectoryWrite($root, 'app/backend/src/b.php', "<?php\nreturn 1;\n");
    subdirectoryWrite($root, 'app/backend/src/c.php', "<?php\nreturn 1;\n");
    subdirectoryWrite($root, 'app/backend/src/d.php', "<?php\nreturn 1;\n");
    subdirectoryWrite($root, 'app/frontend/index.js', "// original\n");
    subdirectoryWrite($root, 'app/frontend/moved.js', "// moved\n");

    subdirectoryGit($root, 'add', '-A');
    subdirectoryGit($root, 'commit', '-q', '-m', 'initial');

    return [
        'root' => $root,
        'project' => $root.'/app/backend',
        'sha' => trim(subdirectoryGit($root, 'rev-parse', 'HEAD')),
    ];
}

beforeEach(function (): void {
    $this->repository = subdirectoryRepository();
});

afterEach(function (): void {
    subdirectoryRemove($this->repository['root']);
});

it('returns project-relative paths for commits made since a sha', function (): void {
    ['root' => $root, 'project' => $project, 'sha' => $sha] = $this->repository;

    subdirectoryWrite($root, 'app/backend/src/a.php', "<?php\nreturn 2;\n");
    subdirectoryWrite($root, 'app/frontend/index.js', "// changed\n");
    subdirectoryGit($root, 'commit', '-q', '-am', 'change');

    $files = new ChangedFiles($project)->since($sha);

    expect($files)->toBe(['src/a.php']);
});

it('returns project-relative paths for modified and untracked files', function (): void {
    ['root' => $root, 'project' => $project] = $this->repository;

    subdirectoryWrite($root, 'app/backend/src/a.php', "<?php\nreturn 2;\n");
    subdirectoryWrite($root, 'app/backend/src/new.php', "<?php\nreturn 3;\n");
    subdirectoryWrite($root, 'app/frontend/index.js', "// changed\n");
    subdirectoryWrite($root, 'app/frontend/untracked.js', "// untracked\n");

    $files = new ChangedFiles($project)->since(null);

    expect($files)->toEqualCanonicalizing(['src/a.php', 'src/new.php']);
});

it('handles renames into, out of and within the project', function (): void {
    ['root' => $root, 'project' => $project] = $this->repository;

    subdirectoryGit($root, 'mv', 'app/backend/src/b.php', 'app/backend/src/b-renamed.php');
    subdirectoryGit($root, 'mv', 'app/backend/src/c.php', 'app/frontend/c.php');
    subdirectoryGit($root, 'mv', 'app/frontend/moved.js', 'app/backend/src/moved.js');

    $files = new ChangedFiles($project)->since(null);

    expect($files)->toEqualCanonicalizing([
        'src/b.php',
        'src/b-renamed.php',
        'src/c.php',
        'src/moved.js',
    ]);
});

it('ignores gitignored files located in the project', function (): void {
    ['root' => $root, 'project' => $project] = $this->repository;

    subdirectoryWrite($root, 'app/backend/ignored.log', 'noise');
    subdirectoryWrite($root, 'app/backend/src/a.php', "<?php\nreturn 2;\n");

    expect(new ChangedFiles($project)->since(null))->toBe(['src/a.php']);
});

it('drops files that are behaviourally unchanged since the sha', function (): void {
    ['root' => $root, 'project' => $project, 'sha' => $sha] = $this->repository;

    subdirectoryWrite($root, 'app/backend/src/a.php', "<?php\nreturn 2;\n");
    subdirectoryGit($root, 'commit', '-q', '-am', 'change');

    subdirectoryWrite($root, 'app/backend/src/a.php', "<?php\nreturn 1;\n");

    expect(new ChangedFiles($project)->since($sha))->toBe([]);
});

it('shows the content of a project file at a sha using a project-relative path', function (): void {
    ['project' => $project, 'sha' => $sha] = $this->repository;

    expect(new Git($project)->show($sha, 'src/a.php'))->toBe("<?php\nreturn 1;\n")
        ->and(new Git($project)->show($sha, 'src/missing.php'))->toBeNull();
});

it('keeps working when the project is the repository root', function (): void {
    ['root' => $root, 'sha' => $sha] = $this->repository;

    subdirectoryWrite($root, '.gitignore', "ignored.log\n");
    subdirectoryWrite($root, 'app/backend/src/a.php', "<?php\nreturn 2;\n");

    expect(new ChangedFiles($root)->since($sha))->toBe(['app/backend/src/a.php'])
        ->and(new Git($root)->show($sha, 'app/backend/src/a.php'))->toBe("<?php\nreturn 1;\n");
});
