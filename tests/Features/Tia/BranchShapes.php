<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

/*
 * Invariant 5 — writes land on the branch that ran and only there — and
 * invariant 6 — nothing is unbounded — under every branch shape git allows.
 */

test('a branch name git allows is a branch key TIA can hold', function (string $branch): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo($branch, new: true);

    $result = $project->pest('--tia');
    $delta = $project->delta();

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->uncached())->toBe(0, $result->describe())
        ->and($project->branchKeys())->toBe(['master', $branch])
        ->and($delta->baselineUntouched('master'))->toBeTrue($delta->summary());
})->with([
    'slashes' => 'feature/deep/nesting',
    'dots' => 'release.1.2.x',
    'unicode' => 'feature-café-日本',
    'digits' => '12345',
    'underscores' => 'feature_x_y',
    'very long' => 'feature-'.str_repeat('x', 180),
])->skipOnWindows();

test('a branch differing from the default only in case gets its own key', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('MASTER-2', new: true);

    $result = $project->pest('--tia');

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($project->branchKeys())->toBe(['master', 'MASTER-2']);
})->skipOnWindows();

test('a branch that only lives on the remote keeps its baseline', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('remote-only', new: true);
    $project->pest('--tia');

    $project->git()->switchTo('master');
    $project->git()->run(['update-ref', 'refs/remotes/origin/remote-only', 'HEAD']);
    $project->git()->run(['branch', '-D', 'remote-only']);

    $project->pest('--tia');

    expect($project->branchKeys())->toBe(['master', 'remote-only']);
})->skipOnWindows();

test('a branch checked out in a worktree keeps its baseline', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('wt-branch', new: true);
    $project->pest('--tia');

    $project->git()->switchTo('master');
    $project->git()->run(['worktree', 'add', '--quiet', $project->path().'-wt', 'wt-branch']);

    $project->pest('--tia');

    $project->git()->run(['worktree', 'remove', '--force', $project->path().'-wt']);

    expect($project->branchKeys())->toBe(['master', 'wt-branch']);
})->skipOnWindows();

test('deleting many branches reclaims every one of their baselines', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    foreach (range(1, 4) as $index) {
        $project->git()->switchTo('feature-'.$index, new: true);
        $project->pest('--tia', ...$arguments);
    }

    expect($project->branchKeys())->toHaveCount(5);

    $project->git()->switchTo('master');

    foreach (range(1, 4) as $index) {
        $project->git()->run(['branch', '-D', 'feature-'.$index]);
    }

    $project->pest('--tia', ...$arguments);

    expect($project->branchKeys())->toBe(['master']);
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a narrowed run does not reclaim anything', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);
    $project->pest('--tia');

    $project->git()->switchTo('master');
    $project->git()->run(['branch', '-D', 'feature-x']);

    $project->snapshot();
    $project->pest('--tia', '--filter=adds two numbers');
    $delta = $project->delta();

    expect($project->branchKeys())->toBe(['master', 'feature-x'])
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();

test('a detached HEAD does not reclaim anything either', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);
    $project->pest('--tia');

    $project->git()->switchTo('master');
    $project->git()->run(['branch', '-D', 'feature-x']);
    $project->git()->detach();

    $project->snapshot();
    $project->pest('--tia');
    $delta = $project->delta();

    expect($project->branchKeys())->toBe(['master', 'feature-x'])
        ->and($delta->isHardSuppressed())->toBeTrue($delta->summary());
})->skipOnWindows();

test('the default branch baseline survives every branch that comes and goes', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);
    $project->pest('--tia');

    $project->git()->switchTo('master');
    $project->git()->run(['branch', '-D', 'feature-x']);

    $project->snapshot();
    $result = $project->pest('--tia');
    $delta = $project->delta();

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($delta->writtenCount())->toBe(0, $delta->summary())
        ->and($delta->added())->toBe(0, $delta->summary())
        ->and($delta->removed())->toBe(0, $delta->summary())
        ->and($project->graph()['baselines']['master']['results'])->toHaveCount(Project::TOTAL_TESTS);
})->skipOnWindows();

test('a project below the git repository root refuses to run and writes nothing', function (array $arguments): void {
    $project = Project::make('master');
    $nested = $project->nested();

    // git addresses paths from the repository root while the graph is
    // project-relative, so the two have to coincide. TIA says so and stops.
    $result = $project->pestIn($nested, '--tia', ...$arguments);

    expect($result->exitCode)->toBe(1, $result->describe())
        ->and($result->output)->toContain('Tia mode requires the git repository root')
        ->and($project->path('.home/.pest'))->not->toBeDirectory()
        ->and($nested.DIRECTORY_SEPARATOR.'.pest')->not->toBeDirectory();
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a repository with no commits says so, and leaves plain runs alone', function (): void {
    $project = Project::withoutGit();
    $project->git()->run(['init', '--quiet']);
    $project->git()->run(['checkout', '--quiet', '-b', 'master']);
    $project->git()->addOrigin();

    // Every git call TIA makes asks about HEAD, which does not exist yet. That
    // used to surface as `requires "git"`, with git installed and working.
    $tia = $project->pest('--tia');

    expect($tia->exitCode)->toBe(1, $tia->describe())
        ->and($tia->output)->toContain('Tia mode requires at least one commit')
        ->and($tia->output)->not->toContain('requires "git"')
        ->and($project->graphExists())->toBeFalse();

    $plain = $project->pest();

    expect($plain->exitCode)->toBe(0, $plain->describe())
        ->and($plain->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->skipOnWindows();

test('a directory with no repository at all still asks for git', function (): void {
    $project = Project::withoutGit();

    $result = $project->pest('--tia');

    expect($result->exitCode)->toBe(1, $result->describe())
        ->and($result->output)->toContain('requires "git"')
        ->and($project->graphExists())->toBeFalse();
})->skipOnWindows();
