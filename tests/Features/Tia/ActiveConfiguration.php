<?php

declare(strict_types=1);

use Pest\Support\Coverage;
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

test('an identical copy of the configuration file under another name does not replay the graph', function (array $arguments): void {
    $project = Project::make('master');
    $project->write('phpunit.copy.xml', (string) file_get_contents($project->path('phpunit.xml')));
    $project->git()->commit('add an identical copy of the configuration');
    $project->seed('master');

    $result = $project->pest('--tia', '--configuration', 'phpunit.copy.xml', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->replayed())->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

function removeConfiguration(Project $project): void
{
    $project->git()->run(['rm', '--quiet', 'phpunit.xml']);
    $project->write('.gitignore', file_get_contents($project->path('.gitignore'))."/.pest*.xml\n");
    $project->git()->commit('use the configuration Pest generates');
    $project->write('vendor/pestphp/pest/.temp/.gitkeep', '');
}

test('a project without a configuration file replays the graph', function (array $arguments): void {
    $project = Project::make('master');
    removeConfiguration($project);
    $project->seed('master');

    $result = $project->pest('--tia', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a leftover generated configuration file does not discard the graph', function (array $arguments): void {
    $project = Project::make('master');
    removeConfiguration($project);
    $project->seed('master');
    $project->write('.pest.xml', (string) file_get_contents($project->path('vendor/pestphp/pest/resources/base-phpunit.xml')));

    $replayed = $project->pest('--tia', ...$arguments);

    expect($replayed->exitCode)->toBe(0, $replayed->describe())
        ->and($replayed->replayed())->toBe(Project::TOTAL_TESTS, $replayed->describe());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a graph recorded under one bootstrap is not replayed under the bootstrap the command line sets', function (array $arguments): void {
    $project = Project::make('master');
    $project->write('tests/AlternateBootstrap.php', "<?php\n\nrequire __DIR__.'/../vendor/autoload.php';\n");
    $project->git()->commit('add an alternate bootstrap');
    $project->seed('master');

    $result = $project->pest('--tia', '--bootstrap', 'tests/AlternateBootstrap.php', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->replayed())->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a graph recorded under the bootstrap the command line sets is replayed under the same bootstrap', function (array $arguments): void {
    $project = Project::make('master');
    $project->write('tests/AlternateBootstrap.php', "<?php\n\nrequire __DIR__.'/../vendor/autoload.php';\n");
    $project->git()->commit('add an alternate bootstrap');

    $project->pest('--tia', '--bootstrap', 'tests/AlternateBootstrap.php', ...$arguments);

    $result = $project->pest('--tia', '--bootstrap', 'tests/AlternateBootstrap.php', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');

test('a graph recorded under a bootstrap whose name looks like an include path is not replayed under that bootstrap and include path', function (array $arguments): void {
    $project = Project::make('master');
    $bootstrap = "<?php\n\nrequire __DIR__.'/../vendor/autoload.php';\n";
    $project->write('tests/Boot.php', $bootstrap);
    $project->write('tests/Boot.php;include-path=app', $bootstrap);
    $project->git()->commit('add two bootstraps');

    $project->pest('--tia', '--bootstrap', 'tests/Boot.php;include-path=app', ...$arguments);

    $result = $project->pest('--tia', '--bootstrap', 'tests/Boot.php', '--include-path', 'app');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->replayed())->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');

test('a graph recorded without an include path is not replayed under the include path the command line sets', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--tia', '--include-path', 'app');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->replayed())->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->skipOnWindows();
