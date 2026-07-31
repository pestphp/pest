<?php

declare(strict_types=1);

use Pest\Plugins\Tia\GitRepository;
use Pest\Plugins\Tia\Storage;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/pest-tia-git-repository-'.bin2hex(random_bytes(4));
    mkdir($this->root.'/apps/api', 0755, true);
    mkdir($this->root.'/services/api', 0755, true);
    mkdir($this->root.'/.git', 0755);
    file_put_contents($this->root.'/.git/config', "[remote \"origin\"]\n\turl = git@github.com:acme/mono.git\n");
});

afterEach(function (): void {
    $remove = function (string $dir) use (&$remove): void {
        foreach (array_diff((array) scandir($dir), ['.', '..']) as $entry) {
            $path = $dir.DIRECTORY_SEPARATOR.$entry;

            is_dir($path) && ! is_link($path) ? $remove($path) : @unlink($path);
        }

        @rmdir($dir);
    };

    $remove($this->root);
});

describe('locate()', function (): void {
    it('finds .git in the path itself', function (): void {
        expect(GitRepository::locate($this->root))->toBe($this->root.DIRECTORY_SEPARATOR.'.git');
    });

    it('walks up to an ancestor .git for subdirectory projects', function (): void {
        expect(GitRepository::locate($this->root.'/apps/api'))
            ->toBe($this->root.DIRECTORY_SEPARATOR.'.git');
    });

    it('stops at a nested repository boundary', function (): void {
        mkdir($this->root.'/apps/api/.git');

        expect(GitRepository::locate($this->root.'/apps/api'))
            ->toBe($this->root.'/apps/api'.DIRECTORY_SEPARATOR.'.git');
    });

    it('treats a .git FILE (worktree, submodule) as the governing entry', function (): void {
        file_put_contents($this->root.'/apps/api/.git', "gitdir: /elsewhere\n");

        expect(GitRepository::locate($this->root.'/apps/api'))
            ->toBe($this->root.'/apps/api'.DIRECTORY_SEPARATOR.'.git');
    });
});

describe('configPath()', function (): void {
    it('resolves the governing repository config for subdirectory projects', function (): void {
        expect(GitRepository::configPath($this->root.'/apps/api'))
            ->toBe($this->root.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'config');
    });

    it('returns null for a .git FILE, matching the previous degradation', function (): void {
        file_put_contents($this->root.'/apps/api/.git', "gitdir: /elsewhere\n");

        expect(GitRepository::configPath($this->root.'/apps/api'))->toBeNull();
    });
});

describe('subdirectoryPrefix()', function (): void {
    it('is empty at the repository root', function (): void {
        expect(GitRepository::subdirectoryPrefix($this->root))->toBeEmpty();
    });

    it('is the slash-terminated project location inside the repository', function (): void {
        expect(GitRepository::subdirectoryPrefix($this->root.'/apps/api'))->toBe('apps/api/');
    });
});

describe('storage keys', function (): void {
    it('gives same-basename sibling projects distinct stores', function (): void {
        $api = Storage::tempDir($this->root.'/apps/api');
        $siblingApi = Storage::tempDir($this->root.'/services/api');

        expect($api)->not->toBe($siblingApi);
    });
});
