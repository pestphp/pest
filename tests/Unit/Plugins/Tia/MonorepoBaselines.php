<?php

declare(strict_types=1);

use Pest\Plugins\Tia\Baselines\GitHubRemote;
use Pest\Plugins\Tia\Baselines\GitLabRemote;
use Pest\Plugins\Tia\Fingerprint;
use Pest\Plugins\Tia\Storage;
use Pest\Plugins\Tia\WatchPatterns;
use Symfony\Component\Process\Process;
use Tests\Fixtures\Tia\GitRepo;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/pest-tia-monorepo-baselines-'.bin2hex(random_bytes(8));
    mkdir($this->root.'/apps/api', 0755, true);
    file_put_contents($this->root.'/apps/api/composer.json', '{}');

    $this->repo = new GitRepo($this->root);
    $this->repo->init();
    $this->repo->addOrigin('git@github.com:acme/mono.git');
});

afterEach(function (): void {
    Storage::useDirectory(null);
    new Process(['rm', '-rf', $this->root])->mustRun();
});

it('detects the baseline repository from a project subdirectory', function (string $host): void {
    $this->repo->setOriginUrl('git@'.$host.':acme/mono.git');

    $remote = $host === 'github.com'
        ? new GitHubRemote(new WatchPatterns)
        : new GitLabRemote(new WatchPatterns);

    expect($remote->detect($this->root.'/apps/api'))->toBe('acme/mono');
})->with(['github.com', 'gitlab.com'])->skipOnWindows();

it('uses the nearest repository for baseline detection', function (string $host): void {
    $this->repo->setOriginUrl('git@'.$host.':acme/mono.git');
    $nested = new GitRepo($this->root.'/apps/api');
    $nested->init();
    $nested->addOrigin('git@'.$host.':acme/nested.git');

    $remote = $host === 'github.com'
        ? new GitHubRemote(new WatchPatterns)
        : new GitLabRemote(new WatchPatterns);

    expect($remote->detect($nested->path))->toBe('acme/nested');
})->with(['github.com', 'gitlab.com'])->skipOnWindows();

it('detects worktree baselines through relative and absolute git pointers', function (bool $absoluteGitDir, bool $absoluteCommonDir, string $host): void {
    $this->repo->setOriginUrl('git@'.$host.':acme/mono.git');
    $worktree = $this->repo->worktree($this->root.'/worktree', 'feature');
    $gitDir = $this->root.'/.git/worktrees/worktree';

    file_put_contents($worktree.'/.git', 'gitdir: '.($absoluteGitDir ? $gitDir : '../.git/worktrees/worktree')."\n");
    file_put_contents($gitDir.'/commondir', ($absoluteCommonDir ? $this->root.'/.git' : '../..')."\n");
    file_put_contents($gitDir.'/config', "[remote \"origin\"]\n\turl = git@".$host.":acme/wrong.git\n");

    $remote = $host === 'github.com'
        ? new GitHubRemote(new WatchPatterns)
        : new GitLabRemote(new WatchPatterns);

    expect($remote->detect($worktree.'/apps/api'))->toBe('acme/mono');
})->with([
    'relative gitdir and commondir' => [false, false],
    'relative gitdir and absolute commondir' => [false, true],
    'absolute gitdir and relative commondir' => [true, false],
    'absolute gitdir and commondir' => [true, true],
])->with(['github.com', 'gitlab.com'])->skipOnWindows();

it('uses the submodule baseline repository instead of its parent remote', function (bool $absoluteGitDir, string $host): void {
    $this->repo->setOriginUrl('git@'.$host.':acme/mono.git');
    mkdir($this->root.'/source/apps/api', 0755, true);
    file_put_contents($this->root.'/source/apps/api/composer.json', '{}');
    $source = new GitRepo($this->root.'/source');
    $source->init();

    $this->repo->run(['-c', 'protocol.file.allow=always', 'submodule', 'add', $source->path, 'packages/module']);
    $submodule = new GitRepo($this->root.'/packages/module');
    $submodule->setOriginUrl('git@'.$host.':acme/module.git');
    $gitDir = $submodule->output(['rev-parse', '--absolute-git-dir']);

    file_put_contents($submodule->path.'/.git', 'gitdir: '.($absoluteGitDir ? $gitDir : '../../.git/modules/packages/module')."\n");

    $remote = $host === 'github.com'
        ? new GitHubRemote(new WatchPatterns)
        : new GitLabRemote(new WatchPatterns);

    expect($remote->detect($submodule->path.'/apps/api'))->toBe('acme/module');
})->with([
    'relative gitdir' => false,
    'absolute gitdir' => true,
])->with(['github.com', 'gitlab.com'])->skipOnWindows();

it('does not use the parent baseline remote when a nested git file is malformed', function (string $host): void {
    $this->repo->setOriginUrl('git@'.$host.':acme/mono.git');
    file_put_contents($this->root.'/apps/api/.git', "not a gitdir\n");

    $remote = $host === 'github.com'
        ? new GitHubRemote(new WatchPatterns)
        : new GitLabRemote(new WatchPatterns);

    expect($remote->detect($this->root.'/apps/api'))->toBeNull();
})->with(['github.com', 'gitlab.com'])->skipOnWindows();

it('keeps caches separate for sibling projects with the same name', function (): void {
    mkdir($this->root.'/services/api', 0755, true);

    expect(Storage::tempDir($this->root.'/apps/api'))
        ->not->toBe(Storage::tempDir($this->root.'/services/api'));
})->skipOnWindows();

it('shares a nested project cache and fingerprint across worktrees', function (): void {
    $worktree = $this->repo->worktree($this->root.'/worktree', 'feature');

    expect(Storage::tempDir($this->root.'/apps/api'))
        ->toBe(Storage::tempDir($worktree.'/apps/api'))
        ->and(Fingerprint::compute($this->root.'/apps/api'))
        ->toBe(Fingerprint::compute($worktree.'/apps/api'));
})->skipOnWindows();
