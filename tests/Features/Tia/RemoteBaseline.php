<?php

declare(strict_types=1);

use Pest\Plugins\Tia\GitHubRepository;
use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

/**
 * @param  callable(array<string, mixed>): array<string, mixed>|null  $mutator
 * @return array{0: Project, 1: array<string, string>}
 */
function tiaPublishedBaseline(
    string $mode = 'ok',
    ?callable $mutator = null,
    ?string $origin = null,
    string $host = GitHubRepository::DEFAULT_HOST,
): array {
    $project = Project::make('master');

    if ($origin !== null) {
        $project->origin($origin);
    }

    $project->seed('master');

    $payload = $project->detachGraph();

    if ($mutator !== null) {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($payload, true);
        $payload = (string) json_encode($mutator($decoded), JSON_UNESCAPED_SLASHES);
    }

    return [$project, $project->gh($mode, $payload, $host)];
}

test('a published baseline is fetched instead of recorded locally', function (): void {
    [$project, $environment] = tiaPublishedBaseline();

    $result = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('Downloading TIA baseline')
        ->and($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($project->graphExists())->toBeTrue()
        ->and($project->ghArgv())->toContain('-R pestphp/tia-fixture')
        ->and($project->ghArgv())->not->toContain('--hostname');
})->skipOnWindows();

test('a published baseline is fetched from a GitHub Enterprise Server remote', function (): void {
    [$project, $environment] = tiaPublishedBaseline(
        origin: 'git@github.foodics.com:pay/capital-api.git',
        host: 'github.foodics.com',
    );

    $result = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('Downloading TIA baseline')
        ->and($result->output)->toContain('github.foodics.com/pay/capital-api')
        ->and($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($project->graphExists())->toBeTrue()
        ->and($project->ghArgv())->toContain('-R github.foodics.com/pay/capital-api')
        ->and($project->ghArgv())->toContain('auth status --hostname github.foodics.com')
        ->and($project->ghArgv())->toContain('api --hostname github.foodics.com repos/pay/capital-api/actions/runs/');
})->skipOnWindows();

test('a remote on a host gh is not authenticated for records locally', function (string $origin): void {
    [$project, $environment] = tiaPublishedBaseline(origin: $origin);

    $result = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->not->toContain('Downloading TIA baseline')
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($project->ghArgv())->not->toContain('run list');
})->with([
    'a GitLab remote' => ['git@gitlab.com:org/repo.git'],
    'an unknown Enterprise Server host' => ['git@ghe.example.com:org/repo.git'],
])->skipOnWindows();

test('a fetched baseline that will not decode is discarded rather than trusted', function (): void {
    [$project, $environment] = tiaPublishedBaseline('corrupt');

    $result = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('The dependency graph could not be read')
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('a fetched baseline recorded against another tree is not used', function (): void {
    [$project, $environment] = tiaPublishedBaseline('ok', function (array $graph): array {
        $graph['fingerprint']['structural']['composer_lock'] = 'a-lockfile-this-project-never-had';

        return $graph;
    });

    $result = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('an artifact without a graph in it fails loudly', function (): void {
    [$project, $environment] = tiaPublishedBaseline('missing-asset');

    $result = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($result->exitCode)->toBe(1, $result->describe())
        ->and($result->output)->toContain('the artifact is missing expected files')
        ->and($project->graphExists())->toBeFalse();
})->skipOnWindows();

test('a baseline that cannot be authenticated for fails loudly', function (): void {
    [$project, $environment] = tiaPublishedBaseline('unauthenticated');

    $result = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($result->exitCode)->toBe(1, $result->describe())
        ->and($result->output)->toContain('is not authenticated')
        ->and($project->graphExists())->toBeFalse();
})->skipOnWindows();

test('a workflow or artifact that is not there fails loudly', function (): void {
    [$project, $environment] = tiaPublishedBaseline('list-404');

    $result = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($result->exitCode)->toBe(1, $result->describe())
        ->and($result->output)->toContain('not found in repo')
        ->and($project->graphExists())->toBeFalse();
})->skipOnWindows();

test('a network failure warns and lets the suite run', function (string $mode): void {
    [$project, $environment] = tiaPublishedBaseline($mode);

    $result = $project->pestWithEnvironment($project->path(), $environment, '--tia', '--baselined');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('network error')
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->with([
    'querying the runs' => ['list-network'],
    'downloading the artifact' => ['download-network'],
])->skipOnWindows();

test('no published baseline yet starts a cooldown, and a corrupt cooldown does not break the run', function (): void {
    [$project, $environment] = tiaPublishedBaseline('no-runs');

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
