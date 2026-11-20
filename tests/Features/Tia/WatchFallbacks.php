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

    expect($result->exitCode)->toBe(0, $result->describe());

    if (str_contains($result->output, 'no coverage driver is available')) {
        expect($result->output)->toContain('Running the full suite to avoid using a stale dependency graph.')
            ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');

        return;
    }

    expect($result->affected())->toBe(4, $result->describe());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('still runs every test without a baseline when defaults are disabled', function (array $arguments): void {
    $project = Project::make('master');
    $project->write('tests/Pest.php', file_get_contents($project->path('tests/Pest.php')).
        "\npest()->tia()->withoutDefaultWatchPatterns();\n");

    $result = $project->pest('--tia', '--filtered', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('runs dependent tests when a watched path is removed', function (array $gitArguments, array $arguments): void {
    $project = Project::make('master');
    $project->write('schema/api.graphql', "type Query { status: String }\n");
    $project->write('tests/Pest.php', file_get_contents($project->path('tests/Pest.php')).
        "\npest()->tia()->withoutDefaultWatchPatterns()->watch(['schema/*.graphql' => 'tests/Unit/GreeterTest.php']);\n");

    $test = 'tests/Unit/GreeterTest.php';
    $project->write($test, str_replace(
        "test('greets a person', function (): void {",
        "test('greets a person', function (): void {\n    expect('schema/api.graphql')->toBeFile();",
        file_get_contents($project->path($test)),
    ));

    $project->git()->commit('add a watched schema and its test');
    $project->seed('master');
    $project->git()->run($gitArguments);
    $project->git()->commit('remove the watched path');

    $result = $project->pest('--tia', '--filtered', ...$arguments);

    expect($result->exitCode)->toBe(1, $result->describe())
        ->and($result->affected())->toBe(2, $result->describe())
        ->and($result->output)->toContain('greets a person', 'schema/api.graphql');
})->with([
    'deleted' => [['rm', '--', 'schema/api.graphql']],
    'renamed' => [['mv', '--', 'schema/api.graphql', 'moved.graphql']],
])->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();
