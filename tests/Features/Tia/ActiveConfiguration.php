<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

function writeAlternateConfiguration(Project $project, string $failOnRisky = 'false'): void
{
    $project->write('phpunit.alternate.xml', str_replace(
        'failOnRisky="true"',
        sprintf('failOnRisky="%s"', $failOnRisky),
        (string) file_get_contents($project->path('phpunit.xml')),
    ));
}

test('naming the default configuration file or its directory still replays the graph', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--tia', ...$arguments);

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe());
})->with([
    'file' => [['--configuration', 'phpunit.xml']],
    'short option' => [['-c', 'phpunit.xml']],
    'equals form' => [['--configuration=phpunit.xml']],
    'directory' => [['--configuration', '.']],
])->skipOnWindows();

test('a graph recorded under one configuration file is not replayed under another', function (array $arguments): void {
    $project = Project::make('master');
    writeAlternateConfiguration($project);
    $project->git()->commit('add an alternate configuration');
    $project->seed('master');

    $result = $project->pest('--tia', '--configuration', 'phpunit.alternate.xml', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->replayed())->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('editing the configuration file in use stops the replay', function (array $arguments): void {
    $project = Project::make('master');
    writeAlternateConfiguration($project);
    $project->git()->commit('add an alternate configuration');
    $project->seed('master', configuration: 'phpunit.alternate.xml');

    $unchanged = $project->pest('--tia', '--configuration', 'phpunit.alternate.xml', ...$arguments);

    expect($unchanged->replayed())->toBe(Project::TOTAL_TESTS, $unchanged->describe());

    writeAlternateConfiguration($project, failOnRisky: 'true');
    $project->git()->commit('change the alternate configuration');

    $edited = $project->pest('--tia', '--configuration', 'phpunit.alternate.xml', ...$arguments);

    expect($edited->exitCode)->toBe(0, $edited->describe())
        ->and($edited->replayed())->toBe(0, $edited->describe())
        ->and($edited->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('editing a configuration file that git ignores stops the replay', function (): void {
    $project = Project::make('master');
    $project->write('.gitignore', "phpunit.xml\n");
    $project->git()->run(['rm', '--cached', '--quiet', 'phpunit.xml']);
    $project->git()->commit('stop tracking phpunit.xml');
    $project->seed('master');

    $unchanged = $project->pest('--tia');

    expect($unchanged->replayed())->toBe(Project::TOTAL_TESTS, $unchanged->describe());

    $project->write('phpunit.xml', str_replace(
        'failOnRisky="true"',
        'failOnRisky="false"',
        (string) file_get_contents($project->path('phpunit.xml')),
    ));

    $edited = $project->pest('--tia');

    expect($edited->exitCode)->toBe(0, $edited->describe())
        ->and($edited->replayed())->toBe(0, $edited->describe())
        ->and($edited->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->skipOnWindows();
