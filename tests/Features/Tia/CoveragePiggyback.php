<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('a coverage report does not found a dependency graph', function (array $arguments): void {
    $project = Project::make('master');

    $project->pest('--tia', ...$arguments);

    expect($project->graphExists())->toBeFalse();
})->with([
    'pest coverage' => [['--coverage']],
    'phpunit coverage report' => [['--coverage-text']],
    'parallel' => [['--coverage', '--parallel', '--processes=2']],
])->skipOnWindows();

test('a plain run after a coverage run records the whole project scope', function (): void {
    $project = Project::make('master');

    $project->pest('--tia', '--coverage');
    $project->pest('--tia');

    $graph = $project->graph();

    if ($graph === null) {
        expect($project->graphExists())->toBeFalse();

        return;
    }

    expect(array_keys($graph['edges']))->toEqualCanonicalizing(array_keys(Project::EDGES))
        ->and($graph['files'])->toContain('tests/Unit/CalculatorTest.php')
        ->and($graph['files'])->toContain('app/Calculator.php');
})->skipOnWindows();

test('a coverage report leaves the edges of an existing graph alone', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->pest('--tia', '--coverage');
    $delta = $project->delta();

    expect($delta->edgesMoved())->toBeFalse($delta->summary())
        ->and($delta->filesMoved())->toBeFalse($delta->summary())
        ->and($delta->removed())->toBe(0, $delta->summary())
        ->and($delta->added())->toBe(0, $delta->summary());
})->skipOnWindows();
