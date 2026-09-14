<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

/**
 * @param  callable(array<string, mixed>): array<string, mixed>|null  $mutator
 * @return array{0: Project, 1: array<string, string>}
 */
function tiaPublishedBaselineGitLab(string $mode = 'ok', ?callable $mutator = null): array
{
    $project = Project::make('master');
    $project->seed('master');

    $payload = $project->detachGraph();

    if ($mutator !== null) {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($payload, true);
        $payload = (string) json_encode($mutator($decoded), JSON_UNESCAPED_SLASHES);
    }

    return [$project, $project->glab($mode, $payload)];
}

test('a self-hosted GitLab baseline is fetched when glab is available', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $payload = $project->detachGraph();

    $environment = $project->glab('ok', $payload, 'gitlab.acme.com');

    $result = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('Downloading TIA baseline')
        ->and($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($project->graphExists())->toBeTrue();
})->skipOnWindows();

test('a published GitLab baseline is fetched instead of recorded locally', function (): void {
    [$project, $environment] = tiaPublishedBaselineGitLab();

    $result = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('Downloading TIA baseline')
        ->and($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($project->graphExists())->toBeTrue();
})->skipOnWindows();

test('a fetched GitLab baseline that will not decode is discarded rather than trusted', function (): void {
    [$project, $environment] = tiaPublishedBaselineGitLab('corrupt');

    $result = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('The dependency graph could not be read')
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('a fetched GitLab baseline recorded against another tree is not used', function (): void {
    [$project, $environment] = tiaPublishedBaselineGitLab('ok', function (array $graph): array {
        $graph['fingerprint']['structural']['composer_lock'] = 'a-lockfile-this-project-never-had';

        return $graph;
    });

    $result = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('a GitLab artifact without a graph in it fails loudly', function (): void {
    [$project, $environment] = tiaPublishedBaselineGitLab('missing-asset');

    $result = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($result->exitCode)->toBe(1, $result->describe())
        ->and($result->output)->toContain('the artifact is missing expected files')
        ->and($project->graphExists())->toBeFalse();
})->skipOnWindows();

test('a GitLab baseline that cannot be authenticated for fails loudly', function (): void {
    [$project, $environment] = tiaPublishedBaselineGitLab('unauthenticated');

    $result = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($result->exitCode)->toBe(1, $result->describe())
        ->and($result->output)->toContain('is not authenticated')
        ->and($project->graphExists())->toBeFalse();
})->skipOnWindows();

test('a GitLab pipeline or job that is not there fails loudly', function (): void {
    [$project, $environment] = tiaPublishedBaselineGitLab('list-404');

    $result = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($result->exitCode)->toBe(1, $result->describe())
        ->and($result->output)->toContain('not found in project')
        ->and($project->graphExists())->toBeFalse();
})->skipOnWindows();

test('a GitLab network failure warns and lets the suite run', function (string $mode): void {
    [$project, $environment] = tiaPublishedBaselineGitLab($mode);

    $result = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('network error')
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->with([
    'querying the runs' => ['list-network'],
    'downloading the artifact' => ['download-network'],
])->skipOnWindows();

test('no published GitLab baseline yet starts a cooldown', function (): void {
    [$project, $environment] = tiaPublishedBaselineGitLab('no-runs');

    $discardGraph = function () use ($project): void {
        if ($project->graphExists()) {
            $project->detachGraph();
        }
    };

    $first = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($first->exitCode)->toBe(0, $first->describe())
        ->and($first->output)->toContain('No baseline published yet')
        ->and($project->graphDir().DIRECTORY_SEPARATOR.'fetch-cooldown.json')->toBeFile();

    $discardGraph();

    $second = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($second->exitCode)->toBe(0, $second->describe())
        ->and($second->output)->toContain('next auto-retry in');

    file_put_contents($project->graphDir().DIRECTORY_SEPARATOR.'fetch-cooldown.json', 'not json{');

    $discardGraph();

    $third = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($third->exitCode)->toBe(0, $third->describe())
        ->and($third->output)->toContain('No baseline published yet')
        ->and($third->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->skipOnWindows();
