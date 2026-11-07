<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

/**
 * @return array{0: Project, 1: string}
 */
function tiaMonorepo(): array
{
    $project = Project::make('master');
    $nested = $project->nested();

    $project->write('nested/resources/views/greeting.blade.php', "<p>Hello</p>\n");
    $project->write('frontend/widget.php', "<?php\n\n\$widget = 1;\n");
    $project->git()->commit('add the nested project and a sibling');

    $project->seedFor($nested, 'master');

    $project->mutateGraph(function (array $graph): array {
        $id = count($graph['files']);
        $graph['files'][$id] = 'resources/views/greeting.blade.php';
        $graph['edges']['tests/Unit/GreeterTest.php'][] = $id;

        return $graph;
    });

    return [$project, $nested];
}

test('a project in a subdirectory of the repository replays its baseline', function (array $arguments): void {
    [$project, $nested] = tiaMonorepo();

    $result = $project->pestIn($nested, '--tia', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->affected())->toBe(0, $result->describe());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a working tree change inside the subdirectory selects the tests that depend on it', function (array $arguments): void {
    [$project, $nested] = tiaMonorepo();

    $project->write('nested/resources/views/greeting.blade.php', "<p>Hi</p>\n");
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->affected())->toBe(2, $result->describe())
        ->and($result->replayed())->toBe(4, $result->describe());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a committed change inside the subdirectory selects the tests that depend on it', function (): void {
    [$project, $nested] = tiaMonorepo();

    $project->write('nested/resources/views/greeting.blade.php', "<p>Hi</p>\n");
    $project->git()->commit('reword the greeting');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->affected())->toBe(2, $result->describe())
        ->and($result->replayed())->toBe(4, $result->describe());
})->skipOnWindows();

test('a change in a sibling package leaves the subdirectory project untouched', function (): void {
    [$project, $nested] = tiaMonorepo();

    $project->write('frontend/widget.php', "<?php\n\n\$widget = 2;\n");
    $project->write('frontend/views/greeting.blade.php', "<p>Hi</p>\n");
    $project->git()->commit('rework the sibling');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->affected())->toBe(0, $result->describe());
})->skipOnWindows();

test('a committed change inside the subdirectory that is undone again selects nothing', function (): void {
    [$project, $nested] = tiaMonorepo();

    $project->write('nested/resources/views/greeting.blade.php', "<p>Hi</p>\n");
    $project->git()->commit('reword the greeting');

    $project->write('nested/resources/views/greeting.blade.php', "<p>Hello</p>\n");
    $project->git()->commit('put the greeting back');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->affected())->toBe(0, $result->describe());
})->skipOnWindows();

test('two projects in the same repository keep their state apart', function (): void {
    $project = Project::make('master');

    $api = $project->nested('services/api');
    $admin = $project->nested('services/admin');

    $project->git()->commit('add both projects');

    expect($project->stateDirFor($api))->not->toBe($project->stateDirFor($admin));
})->skipOnWindows();

test('a nested project fetches the baseline published for it', function (): void {
    [$project, $nested] = tiaMonorepo();

    $environment = $project->gh('ok', $project->detachGraph());

    $result = $project->pestWithEnvironment($nested, $environment, '--tia', '--baselined');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('Downloading TIA baseline')
        ->and($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe());
})->skipOnWindows();

test('a nested project refuses a baseline published by a sibling project', function (): void {
    [$project, $nested] = tiaMonorepo();

    /** @var array<string, mixed> $graph */
    $graph = json_decode($project->detachGraph(), true);
    $graph['fingerprint']['structural']['project_prefix'] = 'services/admin/';

    $environment = $project->gh('ok', (string) json_encode($graph, JSON_UNESCAPED_SLASHES));

    $result = $project->pestWithEnvironment($nested, $environment, '--tia', '--baselined');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('it says when changes outside the project were ignored', function (): void {
    [$project, $nested] = tiaMonorepo();

    $project->write('frontend/widget.php', "<?php\n\n\$widget = 2;\n");
    $project->git()->commit('rework the sibling');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('1 changed file outside this project was ignored');
})->skipOnWindows();
