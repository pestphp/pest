<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('excludes a default fallback in sequential and parallel runs', function (array $arguments): void {
    $project = Project::make('master');
    $project->write('tests/Pest.php', file_get_contents($project->path('tests/Pest.php')).
        "\npest()->tia()->withoutDefaultWatchPatterns(['.env.testing']);\n");
    $project->seed('master');
    $project->write('.env.testing', 'APP_ENV=testing');

    $result = $project->pest('--tia', '--filtered', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('No affected tests found');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('retains custom mappings and PHP edges in sequential and parallel runs', function (array $arguments): void {
    $project = Project::make('master');
    $project->write('tests/Pest.php', file_get_contents($project->path('tests/Pest.php')).
        "\npest()->tia()->withoutDefaultWatchPatterns()->watch(['.env.testing' => 'tests/Unit/GreeterTest.php']);\n");
    $project->seed('master');
    $project->write('.env.testing', 'APP_ENV=testing');

    $result = $project->pest('--tia', '--filtered', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->affected())->toBe(2, $result->describe());

    $project->write('app/Calculator.php', str_replace(
        'return $a + $b;',
        'return ($a + $b);',
        file_get_contents($project->path('app/Calculator.php')),
    ));

    $result = $project->pest('--tia', '--filtered', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->affected())->toBe(4, $result->describe());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('still runs every test without a baseline when defaults are disabled', function (array $arguments): void {
    $project = Project::make('master');
    $project->write('tests/Pest.php', file_get_contents($project->path('tests/Pest.php')).
        "\npest()->tia()->withoutDefaultWatchPatterns();\n");

    $result = $project->pest('--tia', '--filtered', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();
