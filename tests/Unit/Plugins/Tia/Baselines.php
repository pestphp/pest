<?php

declare(strict_types=1);

use Pest\Plugins\Tia\Baselines\GitHubRemote;
use Pest\Plugins\Tia\Baselines\GitLabRemote;
use Pest\Plugins\Tia\WatchPatterns;
use Tests\Fixtures\Tia\GitRepo;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/pest-tia-baseline-monorepo-'.bin2hex(random_bytes(4));
    mkdir($this->root.'/apps/api', 0755, true);
    $this->repo = new GitRepo($this->root);
    $this->repo->init();
});

afterEach(function (): void {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }

    rmdir($this->root);
});

it('detects a baseline repository from a project subdirectory', function (string $host): void {
    $this->repo->addOrigin('git@'.$host.':acme/mono.git');

    $remote = $host === 'github.com'
        ? new GitHubRemote(new WatchPatterns)
        : new GitLabRemote(new WatchPatterns);

    expect($remote->detect($this->root.'/apps/api'))->toBe('acme/mono');
})->with(['github.com', 'gitlab.com']);

it('uses the nearest repository for baseline detection', function (): void {
    $this->repo->addOrigin('git@github.com:acme/mono.git');
    $nested = new GitRepo($this->root.'/apps/api');
    $nested->init();
    $nested->addOrigin('git@github.com:acme/nested.git');

    expect(new GitHubRemote(new WatchPatterns)->detect($nested->path))->toBe('acme/nested');
});

it('detects a baseline repository from a project in a worktree', function (string $host): void {
    $this->repo->addOrigin('git@'.$host.':acme/mono.git');
    file_put_contents($this->root.'/apps/api/composer.json', '{}');
    $this->repo->commit('Add project');
    $worktree = $this->repo->worktree($this->root.'/worktree', 'feature');

    $remote = $host === 'github.com'
        ? new GitHubRemote(new WatchPatterns)
        : new GitLabRemote(new WatchPatterns);

    expect($remote->detect($worktree.'/apps/api'))->toBe('acme/mono');
})->with(['github.com', 'gitlab.com']);

it('detects a submodule baseline repository without using its parent remote', function (string $host): void {
    $this->repo->addOrigin('git@'.$host.':acme/mono.git');
    mkdir($this->root.'/source');
    $source = new GitRepo($this->root.'/source');
    $source->init();
    mkdir($source->path.'/apps/api', 0755, true);
    file_put_contents($source->path.'/apps/api/composer.json', '{}');
    $source->commit('Add project');
    $this->repo->run(['-c', 'protocol.file.allow=always', 'submodule', 'add', $source->path, 'packages/module']);
    $submodule = new GitRepo($this->root.'/packages/module');
    $submodule->setOriginUrl('git@'.$host.':acme/module.git');

    $remote = $host === 'github.com'
        ? new GitHubRemote(new WatchPatterns)
        : new GitLabRemote(new WatchPatterns);

    expect($remote->detect($submodule->path.'/apps/api'))->toBe('acme/module');
})->with(['github.com', 'gitlab.com']);
