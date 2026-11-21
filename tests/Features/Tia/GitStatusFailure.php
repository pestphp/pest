<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('a run without tia finishes when git cannot read the working tree', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    file_put_contents($project->path('.git/index'), 'not an index');

    $result = $project->pest();

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->not->toContain('could not read the git repository')
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->skipOnWindows();
