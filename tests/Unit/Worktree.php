<?php

use Pest\Support\Worktree;

beforeEach(function () {
    $this->originalCwd = getcwd();

    // Create the "real project" directory structure with a vendor autoloader
    $this->realProject = sys_get_temp_dir().'/pest-test-real-'.bin2hex(random_bytes(4));
    mkdir($this->realProject.'/vendor', 0777, true);
    touch($this->realProject.'/vendor/autoload.php');
    $this->autoloadPath = $this->realProject.'/vendor/autoload.php';

    // Create the "worktree" directory with a vendor symlink pointing to the real project
    $this->worktree = sys_get_temp_dir().'/pest-test-worktree-'.bin2hex(random_bytes(4));
    mkdir($this->worktree, 0777, true);
    symlink($this->realProject.'/vendor', $this->worktree.'/vendor');

    // Create a "different vendor" for the wrong-symlink scenario
    $this->differentVendor = sys_get_temp_dir().'/pest-test-different-'.bin2hex(random_bytes(4));
    mkdir($this->differentVendor, 0777, true);
    touch($this->differentVendor.'/autoload.php');
});

afterEach(function () {
    chdir($this->originalCwd);

    // Recursive removal helpers
    $removeDir = function (string $path) use (&$removeDir): void {
        if (! is_dir($path) && ! is_link($path)) {
            return;
        }
        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemPath = $path.'/'.$item;
            if (is_link($itemPath) || ! is_dir($itemPath)) {
                unlink($itemPath);
            } else {
                $removeDir($itemPath);
            }
        }
        rmdir($path);
    };

    // Clean up worktree first (symlink and directories)
    if (is_link($this->worktree.'/vendor')) {
        unlink($this->worktree.'/vendor');
    }
    if (is_dir($this->worktree)) {
        $removeDir($this->worktree);
    }

    // Clean up real project
    if (is_file($this->autoloadPath)) {
        unlink($this->autoloadPath);
    }
    if (is_dir($this->realProject.'/vendor')) {
        rmdir($this->realProject.'/vendor');
    }
    if (is_dir($this->realProject)) {
        rmdir($this->realProject);
    }

    // Clean up different vendor
    if (is_file($this->differentVendor.'/autoload.php')) {
        unlink($this->differentVendor.'/autoload.php');
    }
    if (is_dir($this->differentVendor)) {
        rmdir($this->differentVendor);
    }

    unset($this->originalCwd, $this->realProject, $this->autoloadPath, $this->worktree, $this->differentVendor, $removeDir);
});

it('returns the autoload parent directory when not inside a worktree', function () {
    chdir($this->realProject);

    $root = Worktree::resolveRoot($this->autoloadPath);

    expect($root)->toBe($this->realProject);
});

it('returns the worktree root when vendor is a symlink matching the autoload vendor', function () {
    chdir($this->worktree);

    $root = Worktree::resolveRoot($this->autoloadPath);

    expect($root)->toBe($this->worktree);
});

it('returns the autoload parent directory when vendor symlink points elsewhere', function () {
    symlink($this->differentVendor, $this->worktree.'/vendor-wrong');
    rename($this->worktree.'/vendor', $this->worktree.'/vendor-original');
    rename($this->worktree.'/vendor-wrong', $this->worktree.'/vendor');

    chdir($this->worktree);

    $root = Worktree::resolveRoot($this->autoloadPath);

    expect($root)->toBe($this->realProject);
});

it('returns the autoload parent directory when vendor is a real directory, not a symlink', function () {
    // Replace the worktree's vendor symlink with a real vendor directory
    unlink($this->worktree.'/vendor');
    mkdir($this->worktree.'/vendor', 0777, true);
    touch($this->worktree.'/vendor/autoload.php');

    chdir($this->worktree);

    $root = Worktree::resolveRoot($this->autoloadPath);

    expect($root)->toBe($this->realProject);
});

it('sets APP_BASE_PATH in the environment', function () {
    chdir($this->realProject);

    Worktree::resolveRoot($this->autoloadPath);

    expect($_ENV['APP_BASE_PATH'])->toBe($this->realProject);
});
