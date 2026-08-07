<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

/*
 * An active coverage report owns the coverage driver, so TIA cannot open a
 * session of its own and has to piggyback on PHPUnit's — which is scoped to
 * `phpunit.xml`'s <source>, not to the whole project. Edges recorded that way
 * are missing every source file outside that scope, and a change to one of them
 * would select nothing and replay a pass. Invariant 3 at its most dangerous.
 *
 * The rows below assert what a coverage run may and may not leave behind. They
 * are deliberately silent about exit codes and result counts: `--coverage`
 * itself fails on an interpreter with no driver, so only the graph's fate is
 * the same everywhere.
 */

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

    // Nothing to assert without a driver: there is no graph either way, and the
    // point of the row is that the *plain* run is the one that founds it.
    if ($graph === null) {
        expect($project->graphExists())->toBeFalse();

        return;
    }

    // Self-edges included — they are the first thing a coverage-scoped
    // recording drops, since test files are not in <source>.
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
