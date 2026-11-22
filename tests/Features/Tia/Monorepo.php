<?php

declare(strict_types=1);

use Pest\Plugins\Tia;
use Pest\Support\Coverage;
use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

/**
 * @return array{0: Project, 1: string}
 */
function tiaMonorepo(string $directory = 'nested'): array
{
    $project = Project::make('master');
    $nested = $project->nested($directory);

    $project->write($directory.'/resources/views/greeting.blade.php', "<p>Hello</p>\n");
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

/**
 * @return array{0: Project, 1: string}
 */
function tiaMonorepoWithSharedDependency(): array
{
    [$project, $nested] = tiaMonorepo();

    $project->write('shared/greeting.php', tiaSharedGreeting('Hello, %s!'));
    $project->write('nested/tests/Pest.php', implode("\n", [
        '<?php',
        '',
        'declare(strict_types=1);',
        '',
        "require_once __DIR__.'/../../shared/greeting.php';",
        "require_once __DIR__.'/../app/Calculator.php';",
        "require_once __DIR__.'/../app/Greeter.php';",
        '',
    ]));
    $project->write('nested/app/Greeter.php', implode("\n", [
        '<?php',
        '',
        'declare(strict_types=1);',
        '',
        'namespace Fixture\\App;',
        '',
        'final class Greeter',
        '{',
        '    public function greet(string $name): string',
        '    {',
        '        return \\shared_greeting($name);',
        '    }',
        '}',
        '',
    ]));
    $project->git()->commit('let the project load a shared PHP dependency');

    $project->seedFor($nested, 'master');

    return [$project, $nested];
}

function tiaSharedGreeting(string $format, string $wrapper = ''): string
{
    return implode("\n", [
        '<?php',
        '',
        'declare(strict_types=1);',
        '',
        'function shared_greeting(string $name): string',
        '{',
        sprintf('    return %s(sprintf(%s, $name));', $wrapper, var_export($format, true)),
        '}',
        '',
    ]);
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

test('a project in a directory whose name begins with a space sees its own changes', function (): void {
    [$project, $nested] = tiaMonorepo(' backend');

    $project->write(' backend/resources/views/greeting.blade.php', "<p>Hi</p>\n");
    $project->git()->commit('reword the greeting');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->affected())->toBe(2, $result->describe())
        ->and($result->replayed())->toBe(4, $result->describe());
})->skipOnWindows();

test('a change in a sibling package that holds no PHP leaves the subdirectory project untouched', function (): void {
    [$project, $nested] = tiaMonorepo();

    $project->write('frontend/app.js', "export const widget = 2;\n");
    $project->write('frontend/README.md', "# Frontend\n");
    $project->git()->commit('rework the sibling');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->affected())->toBe(0, $result->describe());
})->skipOnWindows();

test('a committed change in a shared PHP dependency outside the project runs the full suite', function (array $arguments): void {
    [$project, $nested] = tiaMonorepoWithSharedDependency();

    $project->write('shared/greeting.php', tiaSharedGreeting('Goodbye, %s!'));
    $project->git()->commit('reword the shared greeting');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia', ...$arguments);

    expect($result->exitCode)->not->toBe(0, $result->describe())
        ->and($result->output)->toContain('Detected 1 changed PHP file outside this project.')
        ->and($result->replayed())->toBe(0, $result->describe())
        ->and($result->tally())->toContain('2 failed')
        ->and($result->tally())->toContain('4 passed');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a committed change in a shared PHP dependency outside the project runs the full suite once', function (array $arguments): void {
    [$project, $nested] = tiaMonorepoWithSharedDependency();

    $project->write('shared/greeting.php', tiaSharedGreeting('Hello, %s!', 'trim'));
    $project->git()->commit('route the shared greeting through trim');

    $first = $project->pestIn($nested, '--tia', ...$arguments);
    $second = $project->pestIn($nested, '--tia', ...$arguments);

    expect($first->exitCode)->toBe(0, $first->describe())
        ->and($first->replayed())->toBe(0, $first->describe())
        ->and($first->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($second->exitCode)->toBe(0, $second->describe())
        ->and($second->output)->not->toContain('changed PHP file outside this project')
        ->and($second->replayed())->toBe(Project::TOTAL_TESTS, $second->describe());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a committed change in a shared PHP dependency outside the project records the edges of the project again', function (array $arguments): void {
    [$project, $nested] = tiaMonorepoWithSharedDependency();

    $project->write('shared/greeting.php', tiaSharedGreeting('Hello, %s!', 'trim'));
    $project->write('nested/app/Greeter.php', <<<'PHP'
    <?php

    declare(strict_types=1);

    namespace Fixture\App;

    final class Greeter
    {
        public function greet(string $name): string
        {
            (new Calculator)->add(1, 1);

            return \shared_greeting($name);
        }
    }
    PHP);
    $project->git()->commit('let the greeter use the calculator');

    $result = $project->pestIn($nested, '--tia', ...$arguments);

    $graph = $project->graph();
    $edges = array_map(fn (int $id): string => $graph['files'][$id], $graph['edges']['tests/Unit/GreeterTest.php']);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('Detected 1 changed PHP file outside this project.')
        ->and($edges)->toContain('app/Calculator.php');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');

test('an uncommitted change in a shared PHP dependency outside the project runs the full suite and records nothing', function (): void {
    [$project, $nested] = tiaMonorepoWithSharedDependency();

    $recorded = $project->graph()['baselines']['master'] ?? null;

    $project->write('shared/greeting.php', tiaSharedGreeting('Goodbye, %s!'));

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->not->toBe(0, $result->describe())
        ->and($result->output)->toContain('Detected 1 changed PHP file outside this project.')
        ->and($result->output)->toContain('recording nothing')
        ->and($result->replayed())->toBe(0, $result->describe())
        ->and($result->tally())->toContain('2 failed')
        ->and($project->graph()['baselines']['master'] ?? null)->toBe($recorded);
})->skipOnWindows();

test('an uncommitted change in a shared PHP dependency outside the project leaves the coverage cache alone', function (): void {
    [$project, $nested] = tiaMonorepoWithSharedDependency();

    $recording = $project->pestIn($nested, '--tia', '--coverage');

    $cache = $project->graphDir().DIRECTORY_SEPARATOR.'coverage.bin.gz';
    $recorded = file_get_contents($cache);

    $project->write('shared/greeting.php', implode("\n", [
        '<?php',
        '',
        'declare(strict_types=1);',
        '',
        'function shared_greeting(string $name): string',
        '{',
        '    (new \\Fixture\\App\\Calculator)->add(1, 1);',
        '',
        '    return sprintf(\'Hello, %s!\', $name);',
        '}',
        '',
    ]));

    $result = $project->pestIn($nested, '--tia', '--coverage');

    expect($recording->exitCode)->toBe(0, $recording->describe())
        ->and($recorded)->toBeString()
        ->and($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('recording nothing')
        ->and(file_get_contents($cache))->toBe($recorded);
})->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');

test('an uncommitted change in a shared PHP dependency outside the project records no first graph', function (): void {
    [$project, $nested] = tiaMonorepoWithSharedDependency();

    unlink($project->graphDir().DIRECTORY_SEPARATOR.Tia::KEY_GRAPH);

    $project->write('shared/greeting.php', tiaSharedGreeting('Hello, %s!', 'trim'));

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('recording nothing')
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($project->graphExists())->toBeFalse($result->describe());
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

    $project->write('frontend/app.js', "export const widget = 2;\n");
    $project->git()->commit('rework the sibling');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('1 changed file outside this project was ignored');
})->skipOnWindows();
