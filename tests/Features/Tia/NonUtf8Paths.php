<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('a changed file whose name is not valid UTF-8 keeps the graph writable', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write("caf\xe9.txt", "one\n");

    $first = $project->pest('--tia', ...$arguments);

    expect($first->exitCode)->toBe(0, $first->describe())
        ->and($first->output)->not->toContain('Could not write the dependency graph')
        ->and($project->graph()['baselines']['master']['tree'] ?? [])->toHaveKey("caf\xe9.txt");

    $second = $project->pest('--tia', ...$arguments);

    expect($second->exitCode)->toBe(0, $second->describe())
        ->and($second->replayed())->toBe(Project::TOTAL_TESTS, $second->describe());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->onlyOnLinux();
