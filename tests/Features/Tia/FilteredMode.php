<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('re-runs a cached failure on a clean tree', function (): void {
    $project = Project::make('master');
    $project->seed('master', failing: ['adds two numbers']);

    $result = $project->pest('--tia', '--filtered');
    $delta = $project->delta();

    expect($result->output)->toContain('from 1 previously unsuccessful test')
        ->and($result->affected())->toBe(2, $result->describe())
        ->and($result->tally())->toContain('2 passed')
        ->and($delta->writtenCount())->toBe(2, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();

test('an explicit path turns filtered mode off', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--tia', '--filtered', 'tests/Unit');
    $delta = $project->delta();

    expect($result->output)->toContain('TIA does not apply to partial runs')
        ->and($result->output)->not->toContain('No affected tests found')
        ->and($result->tally())->toContain('4 passed')
        ->and($delta->writtenCount())->toBe(4, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();

test('filtered mode runs the whole suite when there is no baseline', function (): void {
    $project = Project::make('master');

    $result = $project->pest('--tia', '--filtered');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($result->output)->not->toContain('No affected tests found');
})->skipOnWindows();

test('filtered mode finds nothing to do in parallel either', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--tia', '--filtered', '--parallel', '--processes=2');
    $delta = $project->delta();

    expect($result->output)->toContain('No affected tests found')
        ->and($result->exitCode)->toBe(0, $result->describe())
        ->and($delta->isHardSuppressed())->toBeTrue($delta->summary());
})->skipOnWindows();

test('a corrupt graph is reported and does not crash the run', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $graph = $project->graphDir().'/graph.json';

    file_put_contents($graph, '{not json');

    $result = $project->pest('--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($result->output)->toContain('The dependency graph could not be read')
        ->and(is_file($graph) ? file_get_contents($graph) : null)->not->toBe('{not json');
})->skipOnWindows();

test('--parallel --retry is refused and leaves the graph alone', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--parallel', '--retry');
    $delta = $project->delta();

    expect($result->exitCode)->not->toBe(0)
        ->and($result->output)->toContain('--retry')
        ->and($delta->isHardSuppressed())->toBeTrue($delta->summary());
})->skipOnWindows();
