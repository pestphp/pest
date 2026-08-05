<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

/**
 * Reading a baseline recorded on another branch.
 *
 * Without the default-branch fallback, the first `--tia` run on every new branch
 * re-runs a whole suite whose results the default branch already holds — once
 * per branch, forever, on any repository not named `main`.
 *
 * @see https://github.com/pestphp/pest/issues/1823
 */
afterEach(function (): void {
    Project::destroyAll();
});

test('replays the default branch baseline on a new branch', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);

    $result = $project->pest('--tia');

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->uncached())->toBe(0, $result->describe())
        ->and($result->exitCode)->toBe(0);
})->skipOnWindows();

test('replays whatever the default branch is called', function (string $defaultBranch): void {
    $project = Project::make($defaultBranch);
    $project->seed($defaultBranch);

    $project->git()->switchTo('feature-x', new: true);

    $result = $project->pest('--tia');

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->uncached())->toBe(0, $result->describe());
})->with(['main', 'master', 'trunk', 'develop'])->skipOnWindows();

test('replays on a second new branch too', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);
    $project->pest('--tia');

    $project->git()->switchTo('master');
    $project->git()->switchTo('feature-y', new: true);

    $result = $project->pest('--tia');

    // The toll is one full run per new branch. It must not come back for the
    // second branch either.
    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->uncached())->toBe(0, $result->describe());
})->skipOnWindows();

test('writes nothing on a second run on the same branch', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);
    $project->pest('--tia');

    $project->snapshot();
    $result = $project->pest('--tia');
    $delta = $project->delta();

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($delta->writtenCount())->toBe(0, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();

test('replays on a branch whose name contains slashes', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('feature/x/y', new: true);

    $result = $project->pest('--tia');

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->uncached())->toBe(0, $result->describe())
        ->and($project->branchKeys())->toContain('feature/x/y');
})->skipOnWindows();

test('replays inside a worktree on a new branch', function (): void {
    $project = Project::make('master');
    $worktree = $project->worktree('feature-worktree');

    // Seeded against the worktree rather than the main checkout: a worktree's
    // `.git` is a file, so `Storage::originIdentity()` cannot read the remote
    // from it and the worktree resolves a storage key of its own. That gap is
    // separate from the branch fallback, and it is the fallback this row is
    // about — the worktree is checked out on a branch the baseline does not
    // name, which is the scenario from the issue.
    $project->seedFor($worktree, 'master');

    $result = $project->pestIn($worktree, '--tia');

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->uncached())->toBe(0, $result->describe());
})->skipOnWindows();
