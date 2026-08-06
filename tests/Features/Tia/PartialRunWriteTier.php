<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('a filtered run rewrites only the test that ran', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--filter=adds two numbers');
    $delta = $project->delta();

    expect($result->tally())->toContain('1 passed')
        ->and($result->output)->not->toContain('TIA does not apply to partial runs')
        ->and($delta->writtenCount())->toBe(1, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();

test('a filtered run under --tia announces that tia does not apply', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--tia', '--filter=adds two numbers');
    $delta = $project->delta();

    expect($result->output)->toContain('TIA does not apply to partial runs')
        ->and($delta->writtenCount())->toBe(1, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();

test('a test suffix narrows the tier even though every test runs', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--tia', '--test-suffix=Test.php');
    $delta = $project->delta();

    expect($result->output)->toContain('TIA does not apply to partial runs')
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($delta->writtenCount())->toBe(Project::TOTAL_TESTS, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();

test('a dirty run narrows to the uncommitted test edit', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/GreeterTest.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        use Fixture\App\Greeter;

        test('greets a person', function (): void {
            expect((new Greeter)->greet('Nuno'))->toBe('Hello, Nuno!');
        });

        test('greets the world', function (): void {
            expect((new Greeter)->greet('world'))->toBe('Hello, world!');
        });

        test('greets again', function (): void {
            expect((new Greeter)->greet('again'))->toBe('Hello, again!');
        });
        PHP);

    $result = $project->pest('--tia', '--dirty');
    $delta = $project->delta();

    expect($result->output)->toContain('TIA does not apply to partial runs')
        ->and($result->tally())->toContain('3 passed')
        ->and($delta->removed())->toBe(0, $delta->summary())
        ->and($delta->structureMoved())->toBeFalse($delta->summary());
})->skipOnWindows();

test('filtered mode yields to an explicit filter', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--tia', '--filtered', '--filter=adds two numbers');
    $delta = $project->delta();

    expect($result->output)->toContain('TIA does not apply to partial runs')
        ->and($result->tally())->toContain('1 passed')
        ->and($delta->writtenCount())->toBe(1, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();

test('an env flag narrows exactly like the option it mirrors', function (string $variable): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pestWithEnvironment($project->path(), [
        $variable => '1',
    ], '--filter=adds two numbers');

    $delta = $project->delta();

    expect($result->output)->toContain('TIA does not apply to partial runs')
        ->and($delta->writtenCount())->toBe(1, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->with(['PEST_TIA', 'PEST_TIA_FILTERED'])->skipOnWindows();

test('a partial run does not purge the graph even with --fresh', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--tia', '--fresh', '--filter=adds two numbers');
    $delta = $project->delta();

    expect($result->output)->toContain('TIA does not apply to partial runs')
        ->and($delta->writtenCount())->toBe(1, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();

test('--no-tia does not stop a partial run from refreshing its own entry', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--no-tia', '--filter=adds two numbers');
    $delta = $project->delta();

    expect($result->output)->not->toContain('TIA does not apply to partial runs')
        ->and($delta->writtenCount())->toBe(1, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();

test('two partial runs each keep the other entry', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->pest('--filter=adds two numbers');
    $project->pest('--filter=greets a person');

    $delta = $project->delta();

    expect($delta->writtenCount())->toBe(2, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();

test('a shard is a partial run', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--tia', '--shard=1/2');
    $delta = $project->delta();

    expect($result->output)->toContain('TIA does not apply to partial runs')
        ->and($delta->removed())->toBe(0, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();

test('a parallel partial run writes nothing at all', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--tia', '--parallel', '--processes=2', '--filter=adds two numbers');
    $delta = $project->delta();

    expect($result->output)->toContain('TIA does not apply to partial runs')
        ->and($delta->writtenCount())->toBe(0, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();
