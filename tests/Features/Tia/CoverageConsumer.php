<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('a mutation run under --tia runs the tests instead of replaying them', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--tia', '--mutate', '--everything', '--min=100');

    expect($result->output)->toContain('TIA is skipped')
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($result->tally())->not->toContain('replayed');
})->skipOnWindows();

test('a mutation run under --tia does not purge the graph it cannot rebuild', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->pest('--tia', '--mutate', '--fresh', '--everything', '--min=100');
    $delta = $project->delta();

    expect($project->graphExists())->toBeTrue()
        ->and($delta->isHardSuppressed())->toBeTrue($delta->summary());
})->skipOnWindows();
