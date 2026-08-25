<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

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

test('replays again once back on the default branch', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);
    $project->pest('--tia');

    $project->git()->switchTo('master');

    $project->snapshot();
    $result = $project->pest('--tia');
    $delta = $project->delta();

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->uncached())->toBe(0, $result->describe())
        ->and($delta->writtenCount())->toBe(0, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();

test('replays on a new branch when tia is enabled by configuration', function (): void {
    $project = Project::make('master', overlay: 'always-enabled');
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);

    $result = $project->pest();

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->uncached())->toBe(0, $result->describe())
        ->and($project->branchKeys())->toBe(['master', 'feature-x']);
})->skipOnWindows();

test('a narrowed run on a new branch does not cost the fallback', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);
    $project->pest(...$arguments);

    $result = $project->pest('--tia');

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->uncached())->toBe(0, $result->describe());
})->with([
    'sequential' => [['--filter=adds two numbers']],
    'parallel' => [['--parallel', '--processes=2', '--filter=adds two numbers']],
])->skipOnWindows();

test('replays inside a worktree on a new branch', function (): void {
    $project = Project::make('master');
    $worktree = $project->worktree('feature-worktree');

    $project->seedFor($worktree, 'master');

    $result = $project->pestIn($worktree, '--tia');

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->uncached())->toBe(0, $result->describe());
})->skipOnWindows();
