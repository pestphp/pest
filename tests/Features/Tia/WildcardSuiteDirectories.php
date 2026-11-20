<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('a new test file under a wildcard suite directory is selected', function (array $arguments): void {
    $project = Project::make('master', overlay: 'wildcard-suite');
    $project->seed('master');

    $project->write('tests/Unit/BrandNewTest.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        test('brand new thing', function (): void {
            expect(true)->toBeTrue();
        });
        PHP);

    $result = $project->pest('--tia', '--filtered', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->not->toContain('No affected tests found')
        ->and($result->affected())->toBe(1, $result->describe())
        ->and($result->tally())->toContain('1 passed');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a test file moved under a wildcard suite directory is selected at its new path', function (array $arguments): void {
    $project = Project::make('master', overlay: 'wildcard-suite');
    $project->seed('master');

    $project->git()->run(['mv', 'tests/Unit/GreeterTest.php', 'tests/Feature/GreeterTest.php']);

    $result = $project->pest('--tia', '--filtered', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->not->toContain('No affected tests found')
        ->and($result->affected())->toBe(2, $result->describe())
        ->and($result->tally())->toContain('2 passed');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();
