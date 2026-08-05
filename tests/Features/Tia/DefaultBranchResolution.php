<?php

declare(strict_types=1);

use Symfony\Component\Process\ExecutableFinder;
use Tests\Fixtures\Tia\Project;

/**
 * How the default branch gets named: declared in `tests/Pest.php`, autodetected
 * from the repository, or not answerable at all.
 */
afterEach(function (): void {
    Project::destroyAll();
});

test('a declared default branch beats autodetection', function (): void {
    // The repository autodetects `develop`, which holds no baseline. Only the
    // declaration in `tests/Pest.php` can reach the `master` one.
    $project = Project::make('develop', overlay: 'configured-default-branch');
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);

    $result = $project->pest('--tia');

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->uncached())->toBe(0, $result->describe());
})->skipOnWindows();

test('a declared default branch that does not exist degrades to a full run', function (): void {
    $project = Project::make('master', overlay: 'unknown-default-branch');
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);

    $result = $project->pest('--tia');

    // Nothing to fall back to, so everything runs — and no baseline is minted
    // under the name that resolved to nothing.
    expect($result->uncached())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->exitCode)->toBe(0, $result->describe())
        ->and($project->branchKeys())->toBe(['master', 'feature-x']);
})->skipOnWindows();

test('a repository with no remote is refused rather than silently re-run', function (): void {
    $project = Project::make('master');

    $project->git()->removeOrigin();
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);

    $result = $project->pest('--tia');

    // Nothing can name the default branch, so every new branch would re-run the
    // whole suite with no explanation. Saying so beats doing that quietly.
    expect($result->output)->toContain('Tia mode requires a repository with a remote.')
        ->and($result->exitCode)->toBe(1, $result->describe());
})->skipOnWindows();

test('a declared default branch stands in for a missing remote', function (): void {
    // The escape hatch the refusal above points at: with the branch named by
    // hand there is nothing left for a remote to answer.
    $project = Project::make('master', overlay: 'configured-default-branch');

    $project->git()->removeOrigin();
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);

    $result = $project->pest('--tia');

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->uncached())->toBe(0, $result->describe());
})->skipOnWindows();

test('tia still requires git', function (): void {
    $project = Project::withoutGit();

    $result = $project->pest('--tia');

    // The soft default-branch resolver runs before the branch is named, and it
    // must not swallow this.
    expect($result->output)->toContain('The feature "Tia mode" requires "git".')
        ->and($result->exitCode)->not->toBe(0);
})->skipOnWindows();

test('a plain run outside a repository creates no baseline', function (): void {
    $project = Project::withoutGit();

    $result = $project->pest();

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($project->graphExists())->toBeFalse()
        ->and($project->graphDir())->not->toBeDirectory();
})->skipOnWindows();

test('the default branch is resolved once per run, not once per test', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);

    $git = (new ExecutableFinder)->find('git');

    expect($git)->not->toBeNull();

    // A `git` first on `PATH` that records what it was asked before handing over
    // to the real one.
    $log = $project->path('git-calls.log');
    $project->write('shim/git', implode("\n", [
        '#!/bin/sh',
        'echo "$@" >> '.escapeshellarg($log),
        'exec '.escapeshellarg((string) $git).' "$@"',
        '',
    ]));
    chmod($project->path('shim/git'), 0755);

    $result = $project->pestWithEnvironment($project->path(), [
        'PATH' => $project->path('shim').':'.getenv('PATH'),
    ], '--tia');

    $calls = file_exists($log) ? explode("\n", trim((string) file_get_contents($log))) : [];
    $resolutions = array_filter($calls, fn (string $call): bool => str_contains($call, 'symbolic-ref')
        || str_contains($call, 'init.defaultBranch'));

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($calls)->not->toBeEmpty('the git shim was never reached')
        ->and($resolutions)->toHaveCount(1, implode("\n", $calls));
})->skipOnWindows();
