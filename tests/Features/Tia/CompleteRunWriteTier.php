<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('a complete run prunes a deleted test', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/GreeterTest.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        use Fixture\App\Greeter;

        test('greets a person', function (): void {
            expect((new Greeter)->greet('Nuno'))->toBe('Hello, Nuno!');
        });
        PHP);

    $result = $project->pest(...$arguments);
    $delta = $project->delta();

    expect($result->tally())->toContain('5 passed')
        ->and($delta->removed())->toBe(1, $delta->summary())
        ->and($delta->added())->toBe(0, $delta->summary())
        ->and($delta->structureMoved())->toBeFalse($delta->summary());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a complete run stays complete when the last test is skipped from a hook', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/GreeterTest.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        use Fixture\App\Greeter;

        beforeEach(function (): void {
            test()->markTestSkipped('not today');
        });

        test('greets a person', function (): void {
            expect((new Greeter)->greet('Nuno'))->toBe('Hello, Nuno!');
        });
        PHP);

    $result = $project->pest(...$arguments);
    $delta = $project->delta();

    expect($result->tally())->toContain('1 skipped')
        ->and($delta->removed())->toBe(1, $delta->summary())
        ->and($delta->added())->toBe(0, $delta->summary());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a complete run records nothing for a test file the graph does not know', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/BrandNewTest.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        test('brand new thing', function (): void {
            expect(true)->toBeTrue();
        });
        PHP);

    $result = $project->pest();
    $delta = $project->delta();

    expect($result->tally())->toContain('7 passed')
        ->and($delta->added())->toBe(0, $delta->summary())
        ->and($delta->removed())->toBe(0, $delta->summary())
        ->and($delta->writtenCount())->toBe(Project::TOTAL_TESTS, $delta->summary());
})->skipOnWindows();

test('a partial run records nothing for a test file the graph does not know', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/BrandNewTest.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        test('brand new thing', function (): void {
            expect(true)->toBeTrue();
        });
        PHP);

    $result = $project->pest('--filter=brand new thing');
    $delta = $project->delta();

    expect($result->tally())->toContain('1 passed')
        ->and($delta->added())->toBe(0, $delta->summary())
        ->and($delta->writtenCount())->toBe(0, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();

test('a truncated run does not prune', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/GreeterTest.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        use Fixture\App\Greeter;

        test('greets a person', function (): void {
            expect((new Greeter)->greet('Nuno'))->toBe('Goodbye, Nuno!');
        });

        test('greets the world', function (): void {
            expect((new Greeter)->greet('world'))->toBe('Hello, world!');
        });
        PHP);

    $result = $project->pest('--bail', ...$arguments);
    $delta = $project->delta();

    expect($result->exitCode)->toBe(1, $result->describe())
        ->and($result->tally())->toContain('1 failed')
        ->and($delta->removed())->toBe(0, $delta->summary())
        ->and($delta->structureMoved())->toBeFalse($delta->summary());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a green bail run is complete', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--bail');
    $delta = $project->delta();

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($delta->writtenCount())->toBe(Project::TOTAL_TESTS, $delta->summary())
        ->and($delta->removed())->toBe(0, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();

test('--no-tia refreshes results without enabling tia', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--tia', '--no-tia', ...$arguments);
    $delta = $project->delta();

    expect($result->output)->not->toContain('Experimental TIA mode enabled')
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($delta->writtenCount())->toBe(Project::TOTAL_TESTS, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a plain run refreshes the results it executed', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest(...$arguments);
    $delta = $project->delta();

    expect($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($delta->writtenCount())->toBe(Project::TOTAL_TESTS, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a parallel replay keeps the recorded time of tests that did not run', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->pest('--tia', '--parallel', '--processes=2');

    $delta = $project->delta();

    expect($delta->writtenCount())->toBe(0, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();

test('a run that never enables tia creates no graph', function (array $arguments): void {
    $project = Project::make('master');

    $result = $project->pest(...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($project->graphExists())->toBeFalse();
})->with([
    'plain' => [[]],
    'filtered' => [['--filter=adds two numbers']],
    'parallel filtered' => [['--parallel', '--processes=2', '--filter=adds two numbers']],
])->skipOnWindows();

test('a test edit narrows to the affected file and replays the rest', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/GreeterTest.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        use Fixture\App\Greeter;

        test('greets a person', function (): void {
            expect((new Greeter)->greet('Nuno'))->toBe('Hello, Nuno!');
            expect((new Greeter)->greet('Nuno'))->toBeString();
        });

        test('greets the world', function (): void {
            expect((new Greeter)->greet('world'))->toBe('Hello, world!');
        });
        PHP);

    $result = $project->pest('--tia');
    $delta = $project->delta();

    expect($result->affected())->toBe(2, $result->describe())
        ->and($result->replayed())->toBe(4, $result->describe())
        ->and($delta->writtenCount())->toBe(2, $delta->summary())
        ->and($delta->edgesMoved())->toBeFalse($delta->summary())
        ->and($delta->removed())->toBe(0, $delta->summary());
})->skipOnWindows();

test('a parallel run merges worker results into the parent baseline', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/GreeterTest.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        use Fixture\App\Greeter;

        test('greets a person', function (): void {
            expect((new Greeter)->greet('Nuno'))->toBe('Hello, Nuno!');
            expect((new Greeter)->greet('Nuno'))->toBeString();
        });

        test('greets the world', function (): void {
            expect((new Greeter)->greet('world'))->toBe('Hello, world!');
        });
        PHP);

    $result = $project->pest('--tia', '--parallel', '--processes=2');
    $delta = $project->delta();

    expect($result->affected())->toBe(2, $result->describe())
        ->and($result->replayed())->toBe(4, $result->describe())
        ->and($delta->writtenCount())->toBe(2, $delta->summary())
        ->and($delta->edgesMoved())->toBeFalse($delta->summary())
        ->and($delta->removed())->toBe(0, $delta->summary());
})->skipOnWindows();
