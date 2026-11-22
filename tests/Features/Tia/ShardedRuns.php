<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('a sharded run replays its slice of the suite', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $replayed = 0;

    foreach (['1/2', '2/2'] as $shard) {
        $result = $project->pest('--tia', '--shard='.$shard, ...$arguments);

        expect($result->output)->not->toContain('TIA does not apply to partial runs')
            ->and($result->output)->toContain('Experimental TIA mode enabled')
            ->and($result->exitCode)->toBe(0, $result->describe())
            ->and($result->affected())->toBe(0, $result->describe())
            ->and($result->uncached())->toBe(0, $result->describe());

        $replayed += $result->replayed();
    }

    expect($replayed)->toBe(Project::TOTAL_TESTS);
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a sharded run executes only the affected tests of its slice', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('app/Greeter.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace Fixture\App;

        final class Greeter
        {
            public function greet(string $name): string
            {
                return 'Hello, '.$name.'!';
            }
        }
        PHP);

    foreach (['first round', 'second round'] as $round) {
        $affected = 0;
        $replayed = 0;

        foreach (['1/2', '2/2'] as $shard) {
            $result = $project->pest('--tia', '--shard='.$shard, ...$arguments);

            expect($result->exitCode)->toBe(0, $result->describe())
                ->and($result->uncached())->toBe(0, $result->describe());

            $affected += $result->affected();
            $replayed += $result->replayed();
        }

        expect($affected)->toBe(2, $round)
            ->and($replayed)->toBe(Project::TOTAL_TESTS - 2, $round);
    }
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a sharded run leaves the baseline of the other shards alone', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('app/Greeter.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace Fixture\App;

        final class Greeter
        {
            public function greet(string $name): string
            {
                return 'Hello, '.$name.'!';
            }
        }
        PHP);

    foreach (['1/2', '2/2'] as $shard) {
        $project->snapshot();

        $result = $project->pest('--tia', '--shard='.$shard, ...$arguments);
        $delta = $project->delta();

        expect($delta->writtenCount())->toBe($result->affected(), $delta->summary())
            ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
    }
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a sharded run does not record a graph that only covers its slice', function (array $arguments): void {
    $project = Project::make('master');

    $result = $project->pest('--tia', '--shard=1/2', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('a shard cannot record the dependency graph of the whole suite')
        ->and($result->output)->not->toContain('TIA does not apply to partial runs')
        ->and($result->replayed())->toBe(0, $result->describe())
        ->and($project->graphExists())->toBeFalse();
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a sharded run does not purge the graph even with --fresh', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--tia', '--fresh', '--shard=1/2');
    $delta = $project->delta();

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->replayed())->toBe(0, $result->describe())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();

test('an explicit filter still makes a sharded run partial', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--tia', '--shard=1/1', '--filter=adds two numbers');
    $delta = $project->delta();

    expect($result->output)->toContain('TIA does not apply to partial runs')
        ->and($result->replayed())->toBe(0, $result->describe())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();

test('a sharded run with a coverage report still runs directly', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--tia', '--shard=1/2', '--coverage');
    $delta = $project->delta();

    expect($result->output)->toContain('TIA does not apply to partial runs')
        ->and($result->replayed())->toBe(0, $result->describe())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();

test('filtered mode narrows a sharded run to the affected tests of its slice', function (): void {
    $project = Project::make('master');
    $project->seed('master', failing: ['adds two numbers']);

    $affected = 0;

    foreach (['1/2', '2/2'] as $shard) {
        $result = $project->pest('--tia', '--filtered', '--shard='.$shard);

        expect($result->exitCode)->toBe(0, $result->describe())
            ->and($result->replayed())->toBe(0, $result->describe());

        $affected += $result->affected();
    }

    expect($affected)->toBe(2);
})->skipOnWindows();
