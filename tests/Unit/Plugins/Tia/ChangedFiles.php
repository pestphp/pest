<?php

declare(strict_types=1);

use Pest\Plugins\Tia\ChangedFiles;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    $this->repoRoot = sys_get_temp_dir().'/pest-tia-changed-files-'.bin2hex(random_bytes(4));
    mkdir($this->repoRoot.'/apps/api/app', 0755, true);
    mkdir($this->repoRoot.'/apps/web', 0755, true);

    $this->git = function (string ...$command): string {
        $process = new Process(['git', ...$command], $this->repoRoot);
        $process->mustRun();

        return trim($process->getOutput());
    };

    ($this->git)('init', '-q', '-b', 'main');
    ($this->git)('config', 'user.email', 'pest@example.com');
    ($this->git)('config', 'user.name', 'Pest');
    ($this->git)('config', 'commit.gpgsign', 'false');

    file_put_contents($this->repoRoot.'/README.md', 'root');
    file_put_contents($this->repoRoot.'/apps/api/composer.lock', 'lock-v1');
    file_put_contents($this->repoRoot.'/apps/api/app/Service.php', "<?php\n\$a = 1;\n");
    file_put_contents($this->repoRoot.'/apps/web/page.tsx', 'web-v1');

    ($this->git)('add', '-A');
    ($this->git)('commit', '-q', '-m', 'baseline');

    $this->baselineSha = ($this->git)('rev-parse', 'HEAD');
});

afterEach(function (): void {
    $remove = function (string $dir) use (&$remove): void {
        foreach (array_diff((array) scandir($dir), ['.', '..']) as $entry) {
            $path = $dir.DIRECTORY_SEPARATOR.$entry;

            is_dir($path) && ! is_link($path) ? $remove($path) : @unlink($path);
        }

        @rmdir($dir);
    };

    $remove($this->repoRoot);
});

describe('repoPrefix()', function (): void {
    it('is empty at the repository root', function (): void {
        expect(new ChangedFiles($this->repoRoot)->repoPrefix())->toBeEmpty();
    });

    it('is the slash-terminated subdirectory path for a monorepo project', function (): void {
        expect(new ChangedFiles($this->repoRoot.'/apps/api')->repoPrefix())->toBe('apps/api/');
    });

    it('is empty outside a git repository', function (): void {
        $outside = sys_get_temp_dir().'/pest-tia-no-repo-'.bin2hex(random_bytes(4));
        mkdir($outside);

        try {
            expect(new ChangedFiles($outside)->repoPrefix())->toBeEmpty();
        } finally {
            @rmdir($outside);
        }
    });
});

describe('since() in a monorepo subdirectory', function (): void {
    it('reports project files as project-relative paths', function (): void {
        file_put_contents($this->repoRoot.'/apps/api/app/Service.php', "<?php\n\$a = 2;\n");

        $changed = new ChangedFiles($this->repoRoot.'/apps/api')->since($this->baselineSha);

        expect($changed)->toBe(['app/Service.php']);
    });

    it('includes untracked project files', function (): void {
        file_put_contents($this->repoRoot.'/apps/api/app/Fresh.php', "<?php\n");

        $changed = new ChangedFiles($this->repoRoot.'/apps/api')->since($this->baselineSha);

        expect($changed)->toBe(['app/Fresh.php']);
    });

    it('ignores changes in sibling projects and at the repository root', function (): void {
        file_put_contents($this->repoRoot.'/README.md', 'root-changed');
        file_put_contents($this->repoRoot.'/apps/web/page.tsx', 'web-v2');

        $changed = new ChangedFiles($this->repoRoot.'/apps/api')->since($this->baselineSha);

        expect($changed)->toBe([]);
    });

    it('detects committed changes to files git would C-quote (non-ASCII names)', function (): void {
        file_put_contents($this->repoRoot.'/apps/api/app/Ærlig.php', "<?php\n\$x = 1;\n");
        ($this->git)('add', '-A');
        ($this->git)('commit', '-q', '-m', 'add utf-8 named file');
        file_put_contents($this->repoRoot.'/apps/api/app/Ærlig.php', "<?php\n\$x = 2;\n");
        ($this->git)('add', '-A');
        ($this->git)('commit', '-q', '-m', 'change utf-8 named file');

        $changed = new ChangedFiles($this->repoRoot.'/apps/api')->since($this->baselineSha);

        expect($changed)->toBe(['app/Ærlig.php']);
    });

    it('sees committed changes since the baseline sha', function (): void {
        file_put_contents($this->repoRoot.'/apps/api/app/Service.php', "<?php\n\$a = 3;\n");
        ($this->git)('add', '-A');
        ($this->git)('commit', '-q', '-m', 'change service');

        $changed = new ChangedFiles($this->repoRoot.'/apps/api')->since($this->baselineSha);

        expect($changed)->toBe(['app/Service.php']);
    });

    it('reports a file renamed OUT of the project subtree as a change to the old path', function (): void {
        ($this->git)('mv', 'apps/api/app/Service.php', 'apps/web/Service.php');
        ($this->git)('commit', '-q', '-m', 'move service out of the api project');

        $changed = new ChangedFiles($this->repoRoot.'/apps/api')->since($this->baselineSha);

        expect($changed)->toBe(['app/Service.php']);
    });

    it('drops cosmetic-only changes, comparing against the baseline blob through the prefix', function (): void {
        file_put_contents($this->repoRoot.'/apps/api/app/Service.php', "<?php\n\$a   =   1;\n");
        ($this->git)('add', '-A');
        ($this->git)('commit', '-q', '-m', 'cosmetic');

        $changed = new ChangedFiles($this->repoRoot.'/apps/api')->since($this->baselineSha);

        expect($changed)->toBe([]);
    });
});

describe('since() at the repository root', function (): void {
    it('keeps the existing root-level behaviour', function (): void {
        file_put_contents($this->repoRoot.'/README.md', 'root-changed');
        file_put_contents($this->repoRoot.'/apps/api/app/Service.php', "<?php\n\$a = 2;\n");

        $changed = new ChangedFiles($this->repoRoot)->since($this->baselineSha);

        sort($changed);

        expect($changed)->toBe(['README.md', 'apps/api/app/Service.php']);
    });
});

describe('contentAtSha()', function (): void {
    it('resolves project-relative paths through the subdirectory prefix', function (): void {
        $content = new ChangedFiles($this->repoRoot.'/apps/api')
            ->contentAtSha($this->baselineSha, 'composer.lock');

        expect($content)->toBe('lock-v1');
    });

    it('returns null for paths missing from the commit', function (): void {
        $content = new ChangedFiles($this->repoRoot.'/apps/api')
            ->contentAtSha($this->baselineSha, 'nope.txt');

        expect($content)->toBeNull();
    });
});
