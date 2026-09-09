<?php

declare(strict_types=1);

use Pest\Plugins\Tia\GitHubRepository;

it('parses a github.com remote', function (string $url): void {
    $repository = GitHubRepository::fromRemoteUrl($url);

    expect($repository)->not->toBeNull()
        ->and($repository->host)->toBe('github.com')
        ->and($repository->name)->toBe('pestphp/pest')
        ->and($repository->isDefaultHost())->toBeTrue();
})->with([
    'git@github.com:pestphp/pest.git',
    'git@github.com:pestphp/pest',
    'https://github.com/pestphp/pest.git',
    'https://github.com/pestphp/pest',
    'https://github.com/pestphp/pest/',
    'http://github.com/pestphp/pest',
    'ssh://git@github.com/pestphp/pest.git',
    'ssh://git@github.com:22/pestphp/pest.git',
    'ssh://github.com/pestphp/pest',
]);

it('parses a github enterprise server remote', function (string $url, string $host, string $name): void {
    $repository = GitHubRepository::fromRemoteUrl($url);

    expect($repository)->not->toBeNull()
        ->and($repository->host)->toBe($host)
        ->and($repository->name)->toBe($name)
        ->and($repository->isDefaultHost())->toBeFalse();
})->with([
    ['git@github.foodics.com:pay/capital-api.git', 'github.foodics.com', 'pay/capital-api'],
    ['https://github.example.com/org/repo', 'github.example.com', 'org/repo'],
    ['https://github.example.com:8443/org/repo.git', 'github.example.com', 'org/repo'],
    ['ssh://git@ghe.example.com:2222/org/repo.git', 'ghe.example.com', 'org/repo'],
    ['ssh://GHE.EXAMPLE.COM/org/repo', 'ghe.example.com', 'org/repo'],
]);

it('rejects a remote that is not a repository url', function (string $url): void {
    expect(GitHubRepository::fromRemoteUrl($url))->toBeNull();
})->with([
    '/an/absolute/path',
    '../a/relative/path',
    'file:///an/absolute/path',
    'git://github.com/pestphp/pest.git',
    'https://github.com/pestphp',
    'git@github.com:pestphp',
]);

it('reads the origin remote of a project', function (): void {
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('pest-tia-origin-', true);

    mkdir($root.DIRECTORY_SEPARATOR.'.git', 0755, true);

    file_put_contents($root.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'config', <<<'CONFIG'
        [core]
        	repositoryformatversion = 0
        [remote "origin"]
        	url = git@github.foodics.com:pay/capital-api.git
        	fetch = +refs/heads/*:refs/remotes/origin/*
        CONFIG);

    $repository = GitHubRepository::fromProjectRoot($root);

    expect($repository)->not->toBeNull()
        ->and($repository->host)->toBe('github.foodics.com')
        ->and($repository->name)->toBe('pay/capital-api');
});

it('has no origin remote to read without a git directory', function (): void {
    expect(GitHubRepository::fromProjectRoot(sys_get_temp_dir()))->toBeNull();
});

it('leaves the gh arguments of a github.com repository untouched', function (): void {
    $repository = GitHubRepository::fromRemoteUrl('git@github.com:pestphp/pest.git');

    expect($repository->qualifiedName())->toBe('pestphp/pest')
        ->and($repository->hostnameArguments())->toBeEmpty();
});

it('qualifies the gh arguments of a github enterprise server repository', function (): void {
    $repository = GitHubRepository::fromRemoteUrl('git@github.foodics.com:pay/capital-api.git');

    expect($repository->qualifiedName())->toBe('github.foodics.com/pay/capital-api')
        ->and($repository->hostnameArguments())->toBe(['--hostname', 'github.foodics.com']);
});
