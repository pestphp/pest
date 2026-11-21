<?php

declare(strict_types=1);

use Pest\Plugins\Tia;
use Pest\Support\Coverage;
use Tests\Fixtures\Tia\GitRepo;
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

test('a change in a sibling package the project autoloads runs the full suite', function (array $arguments): void {
    [$project, $nested] = tiaMonorepo();

    $manifest = json_decode((string) file_get_contents($nested.'/composer.json'), true);
    $manifest['autoload']['psr-4']['Shared\\'] = '../packages/shared/src';
    $project->write('nested/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");
    $project->git()->commit('let the project load a sibling package');
    $project->seedFor($nested, 'master');

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 2;\n");
    $project->git()->commit('rework the sibling package');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('this project loads from outside its root')
        ->and($result->replayed())->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a non-php change in a sibling package the project does not load still replays', function (): void {
    [$project, $nested] = tiaMonorepo();

    $project->write('packages/shared/resources/shared.json', "{\"shared\": 2}\n");
    $project->git()->commit('rework an unrelated sibling package');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->not->toContain('this project loads from outside its root')
        ->and($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe());
})->skipOnWindows();

test('a change in an autoload file outside the project runs the full suite', function (): void {
    [$project, $nested] = tiaMonorepo();

    $manifest = json_decode((string) file_get_contents($nested.'/composer.json'), true);
    $manifest['autoload']['files'][] = '../packages/shared/bootstrap.php';
    $project->write('nested/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

    $project->write('packages/shared/bootstrap.php', "<?php\n\n\$booted = 1;\n");
    $project->git()->commit('let the project load a sibling bootstrap file');
    $project->seedFor($nested, 'master');

    $project->write('packages/shared/bootstrap.php', "<?php\n\n\$booted = 2;\n");
    $project->git()->commit('rework the sibling bootstrap file');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('this project loads from outside its root')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('a change in the phpunit bootstrap outside the project runs the full suite', function (): void {
    [$project, $nested] = tiaMonorepo();

    $project->write('packages/shared/bootstrap.php', "<?php\n\nrequire __DIR__.'/../../nested/vendor/autoload.php';\n");
    $project->write('nested/phpunit.xml', str_replace(
        'bootstrap="vendor/autoload.php"',
        'bootstrap="../packages/shared/bootstrap.php"',
        (string) file_get_contents($nested.'/phpunit.xml'),
    ));
    $project->git()->commit('point the project at a sibling bootstrap file');
    $project->seedFor($nested, 'master');

    $project->write('packages/shared/bootstrap.php', "<?php\n\nrequire __DIR__.'/../../nested/vendor/autoload.php';\n\n\$booted = 2;\n");
    $project->git()->commit('rework the sibling bootstrap file');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('this project loads from outside its root')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('deleting a declared external package runs the full suite', function (): void {
    [$project, $nested] = tiaMonorepo();

    $manifest = json_decode((string) file_get_contents($nested.'/composer.json'), true);
    $manifest['autoload']['psr-4']['Shared\\'] = '../packages/shared/src';
    $project->write('nested/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");
    $project->git()->commit('let the project load a sibling package');
    $project->seedFor($nested, 'master');

    $project->git()->run(['rm', '-r', '--quiet', 'packages/shared']);
    $project->git()->commit('delete the sibling package');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('this project loads from outside its root')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('a change in an external test suite runs the full suite', function (): void {
    [$project, $nested] = tiaMonorepo();

    $project->write('shared-tests/support.php', "<?php\n\n\$support = 1;\n");

    $project->write('nested/phpunit.xml', str_replace(
        '<directory suffix="Test.php">./tests</directory>',
        '<directory suffix="Test.php">./tests</directory>'."\n".'      <directory suffix="Test.php">../shared-tests</directory>',
        (string) file_get_contents($nested.'/phpunit.xml'),
    ));
    $project->git()->commit('add an external test suite');
    $project->seedFor($nested, 'master');

    $project->write('shared-tests/support.php', "<?php\n\n\$support = 2;\n");
    $project->git()->commit('rework the external test suite');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('this project loads from outside its root')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('a change under a wildcard path repository runs the full suite', function (): void {
    [$project, $nested] = tiaMonorepo();

    $manifest = json_decode((string) file_get_contents($nested.'/composer.json'), true);
    $manifest['repositories'][] = ['type' => 'path', 'url' => '../packages/*/src'];
    $project->write('nested/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");
    $project->git()->commit('declare a wildcard path repository');
    $project->seedFor($nested, 'master');

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 2;\n");
    $project->git()->commit('rework the wildcard package');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('this project loads from outside its root')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('a change under a symlink that leaves the project runs the full suite', function (): void {
    [$project, $nested] = tiaMonorepo();

    $manifest = json_decode((string) file_get_contents($nested.'/composer.json'), true);
    $manifest['autoload']['psr-4']['Shared\\'] = 'shared';
    $project->write('nested/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");
    symlink($project->path('packages/shared/src'), $nested.'/shared');
    $project->git()->commit('link a sibling package into the project');
    $project->seedFor($nested, 'master');

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 2;\n");
    $project->git()->commit('rework the linked package');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('this project loads from outside its root')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('a change declared only by the configuration argument runs the full suite', function (): void {
    [$project, $nested] = tiaMonorepo();

    $project->write('packages/shared/bootstrap.php', "<?php\n\nrequire __DIR__.'/../../nested/vendor/autoload.php';\n");
    $project->write('nested/phpunit.ci.xml', str_replace(
        'bootstrap="vendor/autoload.php"',
        'bootstrap="../packages/shared/bootstrap.php"',
        (string) file_get_contents($nested.'/phpunit.xml'),
    ));
    $project->git()->commit('add a second configuration');
    $project->seedFor($nested, 'master', arguments: ['-c', 'phpunit.ci.xml']);

    $project->write('packages/shared/bootstrap.php', "<?php\n\nrequire __DIR__.'/../../nested/vendor/autoload.php';\n\n\$booted = 2;\n");
    $project->git()->commit('rework the sibling bootstrap file');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia', '-c', 'phpunit.ci.xml');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('this project loads from outside its root')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('a run under a different configuration does not reuse the baseline', function (): void {
    [$project, $nested] = tiaMonorepo();

    $project->write('nested/phpunit.ci.xml', str_replace(
        'failOnRisky="true"',
        'failOnRisky="false"',
        (string) file_get_contents($nested.'/phpunit.xml'),
    ));
    $project->git()->commit('add a second configuration');
    $project->seedFor($nested, 'master');
    $project->snapshot();

    $replayed = $project->pestIn($nested, '--tia');
    $selected = $project->pestIn($nested, '--tia', '-c', 'phpunit.ci.xml');

    expect($replayed->replayed())->toBe(Project::TOTAL_TESTS, $replayed->describe())
        ->and($selected->replayed())->toBe(0, $selected->describe())
        ->and($selected->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->skipOnWindows();

test('removing an external declaration with its package does not replay', function (): void {
    [$project, $nested] = tiaMonorepo();

    $manifest = json_decode((string) file_get_contents($nested.'/composer.json'), true);
    $manifest['autoload']['psr-4']['Shared\\'] = '../packages/shared/src';
    $project->write('nested/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");
    $project->git()->commit('let the project load a sibling package');
    $project->seedFor($nested, 'master');

    unset($manifest['autoload']['psr-4']['Shared\\']);
    $project->write('nested/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));
    $project->git()->run(['rm', '-r', '--quiet', 'packages/shared']);
    $project->git()->commit('drop the sibling package and its mapping');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia');

    expect($result->replayed())->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->skipOnWindows();

test('an external change runs the full suite once', function (): void {
    [$project, $nested] = tiaMonorepo();

    $manifest = json_decode((string) file_get_contents($nested.'/composer.json'), true);
    $manifest['autoload']['psr-4']['Shared\\'] = '../packages/shared/src';
    $project->write('nested/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");
    $project->git()->commit('let the project load a sibling package');
    $project->seedFor($nested, 'master');

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 2;\n");
    $project->git()->commit('rework the sibling package');

    $first = $project->pestIn($nested, '--tia');
    $second = $project->pestIn($nested, '--tia');

    expect($first->output)->toContain('this project loads from outside its root')
        ->and($first->replayed())->toBe(0, $first->describe())
        ->and($second->replayed())->toBe(Project::TOTAL_TESTS, $second->describe());
})->skipOnWindows();

test('a relative configuration path resolves against the directory the command ran in', function (): void {
    [$project, $nested] = tiaMonorepo();

    $project->write('packages/shared/bootstrap.php', "<?php\n\nrequire __DIR__.'/../../nested/vendor/autoload.php';\n");
    $project->write('nested/phpunit.ci.xml', str_replace(
        'bootstrap="vendor/autoload.php"',
        'bootstrap="../packages/shared/bootstrap.php"',
        (string) file_get_contents($nested.'/phpunit.xml'),
    ));
    $project->git()->commit('add a second configuration');
    $project->seedFor($nested, 'master', arguments: ['-c', $project->path('nested/phpunit.ci.xml')]);

    $project->write('packages/shared/bootstrap.php', "<?php\n\nrequire __DIR__.'/../../nested/vendor/autoload.php';\n\n\$booted = 2;\n");
    $project->git()->commit('rework the sibling bootstrap file');
    $project->snapshot();

    $result = $project->pestFrom($nested, $project->path(), '--tia', '-c', 'nested/phpunit.ci.xml');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('this project loads from outside its root')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('a change in a bootstrap file that the command line names runs the full suite', function (): void {
    [$project, $nested] = tiaMonorepo();

    $project->write('packages/shared/bootstrap.php', "<?php\n\nrequire __DIR__.'/../../nested/vendor/autoload.php';\n");
    $project->git()->commit('add a sibling bootstrap file');
    $project->seedFor($nested, 'master', arguments: ['--bootstrap', '../packages/shared/bootstrap.php']);

    $project->write('packages/shared/bootstrap.php', "<?php\n\nrequire __DIR__.'/../../nested/vendor/autoload.php';\n\n\$booted = 2;\n");
    $project->git()->commit('rework the sibling bootstrap file');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia', '--bootstrap', '../packages/shared/bootstrap.php');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('this project loads from outside its root')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('a change under an include path that the command line names runs the full suite', function (): void {
    [$project, $nested] = tiaMonorepo();

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");
    $project->git()->commit('add a sibling package');
    $project->seedFor($nested, 'master', arguments: ['--include-path', '../packages/shared/src']);

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 2;\n");
    $project->git()->commit('rework the sibling package');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia', '--include-path', '../packages/shared/src');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('this project loads from outside its root')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('a change under an include path that the configuration names runs the full suite', function (): void {
    [$project, $nested] = tiaMonorepo();

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");
    $project->write('nested/phpunit.xml', str_replace(
        '</phpunit>',
        "  <php>\n    <includePath>../packages/shared/src</includePath>\n  </php>\n</phpunit>",
        (string) file_get_contents($nested.'/phpunit.xml'),
    ));
    $project->git()->commit('point the configuration at a sibling include path');
    $project->seedFor($nested, 'master');

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 2;\n");
    $project->git()->commit('rework the sibling package');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('this project loads from outside its root')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('a submodule that a commit moves runs the full suite', function (): void {
    [$project, $nested] = tiaMonorepo();

    $library = $project->path().'-library';
    mkdir($library, 0755, true);
    $libraryRepo = new GitRepo($library);
    file_put_contents($library.'/Shared.php', "<?php\n\n\$shared = 1;\n");
    $libraryRepo->init('master');

    $manifest = json_decode((string) file_get_contents($nested.'/composer.json'), true);
    $manifest['autoload']['psr-4']['Shared\\'] = '../packages/shared';
    $project->write('nested/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

    $project->git()->run(['-c', 'protocol.file.allow=always', 'submodule', 'add', '--quiet', $library, 'packages/shared']);
    $project->git()->commit('add the library as a submodule');
    $project->seedFor($nested, 'master');

    file_put_contents($library.'/Shared.php', "<?php\n\n\$shared = 2;\n");
    $libraryRepo->commit('rework the library');

    new GitRepo($project->path('packages/shared'))->run(['-c', 'protocol.file.allow=always', 'pull', '--quiet', 'origin', 'master']);
    $project->git()->commit('bump the submodule');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('this project loads from outside its root')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('a run from the repository root reads the configuration it finds there', function (): void {
    [$project, $nested] = tiaMonorepo();

    $project->write('packages/shared/bootstrap.php', "<?php\n\nrequire __DIR__.'/../../nested/vendor/autoload.php';\n");
    $project->write('phpunit.xml', str_replace(
        'bootstrap="vendor/autoload.php"',
        'bootstrap="packages/shared/bootstrap.php"',
        (string) file_get_contents($nested.'/phpunit.xml'),
    ));
    $project->git()->commit('add a configuration at the repository root');
    $project->seedFor($nested, 'master', arguments: ['-c', $project->path('phpunit.xml')]);

    $project->write('packages/shared/bootstrap.php', "<?php\n\nrequire __DIR__.'/../../nested/vendor/autoload.php';\n\n\$booted = 2;\n");
    $project->git()->commit('rework the sibling bootstrap file');
    $project->snapshot();

    $result = $project->pestFrom($nested, $project->path(), '--tia');

    expect($result->output)->toContain('this project loads from outside its root')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('a project that loads from outside its repository never replays', function (): void {
    [$project, $nested] = tiaMonorepo();

    $outside = $project->path().'-outside';
    mkdir($outside, 0755, true);
    file_put_contents($outside.'/bootstrap.php', "<?php\n\nrequire '".$nested."/vendor/autoload.php';\n");

    $manifest = json_decode((string) file_get_contents($nested.'/composer.json'), true);
    $manifest['autoload']['files'][] = $outside.'/bootstrap.php';
    $project->write('nested/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));
    $project->git()->commit('load a file from outside the repository');
    $project->seedFor($nested, 'master');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('from outside its repository')
        ->and($result->replayed())->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->skipOnWindows();

test('an uncommitted change outside the project records nothing', function (): void {
    [$project, $nested] = tiaMonorepo();

    $manifest = json_decode((string) file_get_contents($nested.'/composer.json'), true);
    $manifest['autoload']['psr-4']['Shared\\'] = '../packages/shared/src';
    $project->write('nested/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");
    $project->git()->commit('let the project load a sibling package');
    $project->seedFor($nested, 'master');

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 2;\n");
    $project->snapshot();

    $dirty = $project->pestIn($nested, '--tia');
    $delta = $project->delta();

    expect($dirty->output)->toContain('recording nothing')
        ->and($dirty->replayed())->toBe(0, $dirty->describe())
        ->and($delta->writtenCount())->toBe(0, $delta->summary());

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");

    $restored = $project->pestIn($nested, '--tia');

    expect($restored->replayed())->toBe(Project::TOTAL_TESTS, $restored->describe());
})->skipOnWindows();

test('a committed change under a wildcard source directory runs the full suite', function (): void {
    [$project, $nested] = tiaMonorepo();

    $project->write('nested/phpunit.xml', str_replace(
        '<directory suffix=".php">./app</directory>',
        '<directory suffix=".php">./app</directory>'."\n".'      <directory suffix=".php">../packages/*/src</directory>',
        (string) file_get_contents($nested.'/phpunit.xml'),
    ));
    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");
    $project->git()->commit('watch every package source directory');
    $project->seedFor($nested, 'master');

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 2;\n");
    $project->git()->commit('rework the package');
    $project->snapshot();

    $result = $project->pestIn($nested, '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('this project loads from outside its root')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('an uncommitted change outside the project records nothing on a first run', function (array $arguments): void {
    [$project, $nested] = tiaMonorepo();

    $manifest = json_decode((string) file_get_contents($nested.'/composer.json'), true);
    $manifest['autoload']['psr-4']['Shared\\'] = '../packages/shared/src';
    $project->write('nested/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");
    $project->git()->commit('let the project load a sibling package');

    $project->detachGraph();

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 2;\n");

    $result = $project->pestIn($nested, '--tia', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('recording nothing')
        ->and($project->graphExists())->toBeFalse();
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('an uncommitted change outside the project records nothing on a fresh run', function (): void {
    [$project, $nested] = tiaMonorepo();

    $manifest = json_decode((string) file_get_contents($nested.'/composer.json'), true);
    $manifest['autoload']['psr-4']['Shared\\'] = '../packages/shared/src';
    $project->write('nested/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");
    $project->git()->commit('let the project load a sibling package');
    $project->seedFor($nested, 'master');
    $project->snapshot();

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 2;\n");

    $result = $project->pestIn($nested, '--tia', '--fresh');
    $delta = $project->delta();

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('recording nothing')
        ->and($delta->writtenCount())->toBe(0, $delta->summary());
})->skipOnWindows();

test('an uncommitted change outside the project records no result on a partial run', function (): void {
    [$project, $nested] = tiaMonorepo();

    $manifest = json_decode((string) file_get_contents($nested.'/composer.json'), true);
    $manifest['autoload']['psr-4']['Shared\\'] = '../packages/shared/src';
    $project->write('nested/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");
    $project->git()->commit('let the project load a sibling package');
    $project->seedFor($nested, 'master', failing: ['adds two numbers']);

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 2;\n");
    $project->snapshot();

    $dirty = $project->pestIn($nested, '--tia', '--filter=adds two numbers');
    $delta = $project->delta();

    expect($dirty->tally())->toContain('1 passed')
        ->and($delta->writtenCount())->toBe(0, $delta->summary());

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");
    $project->snapshot();

    $clean = $project->pestIn($nested, '--tia', '--filter=adds two numbers');
    $delta = $project->delta();

    expect($clean->tally())->toContain('1 passed')
        ->and($delta->writtenCount())->toBe(1, $delta->summary());
})->skipOnWindows();

test('editing a configuration that git ignores stops the replay', function (): void {
    [$project, $nested] = tiaMonorepo();

    $project->write('nested/.gitignore', "phpunit.xml\n");
    $project->git()->run(['rm', '--cached', '--quiet', 'nested/phpunit.xml']);
    $project->git()->commit('stop tracking the configuration');
    $project->seedFor($nested, 'master');
    $project->snapshot();

    $replayed = $project->pestIn($nested, '--tia');

    $project->write('nested/phpunit.xml', str_replace(
        'failOnRisky="true"',
        'failOnRisky="false"',
        (string) file_get_contents($nested.'/phpunit.xml'),
    ));

    $edited = $project->pestIn($nested, '--tia');

    expect($replayed->replayed())->toBe(Project::TOTAL_TESTS, $replayed->describe())
        ->and($edited->replayed())->toBe(0, $edited->describe())
        ->and($edited->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->skipOnWindows();

test('a run with --no-configuration does not replay a graph recorded with one', function (): void {
    [$project, $nested] = tiaMonorepo();

    $project->seedFor($nested, 'master');
    $project->snapshot();

    $replayed = $project->pestIn($nested, '--tia');
    $suppressed = $project->pestIn($nested, '--tia', '--no-configuration');

    expect($replayed->replayed())->toBe(Project::TOTAL_TESTS, $replayed->describe())
        ->and($suppressed->replayed())->toBe(0, $suppressed->describe());
})->skipOnWindows();

test('a run without tia finishes when git cannot read the working tree of a project that loads from outside its root', function (): void {
    [$project, $nested] = tiaMonorepo();

    $manifest = json_decode((string) file_get_contents($nested.'/composer.json'), true);
    $manifest['autoload']['psr-4']['Shared\\'] = '../packages/shared/src';
    $project->write('nested/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");
    $project->git()->commit('let the project load a sibling package');
    $project->seedFor($nested, 'master');

    file_put_contents($project->path('.git/index'), 'not an index');

    $result = $project->pestIn($nested);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->not->toContain('could not read the git repository')
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->skipOnWindows();

test('a committed change outside the project without a coverage driver stores the results of the full suite', function (array $arguments): void {
    [$project, $nested] = tiaMonorepo();

    $manifest = json_decode((string) file_get_contents($nested.'/composer.json'), true);
    $manifest['autoload']['psr-4']['Shared\\'] = '../packages/shared/src';
    $project->write('nested/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");
    $project->git()->commit('let the project load a sibling package');
    $project->seedFor($nested, 'master', failing: ['adds two numbers']);

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 2;\n");
    $project->git()->commit('rework the sibling package');

    $result = $project->pestWithEnvironment($nested, ['XDEBUG_MODE' => 'off'], '--tia', ...$arguments);

    $testId = Project::testId('tests/Unit/CalculatorTest.php', 'adds two numbers');
    $stored = $project->graph()['baselines']['master']['results'][$testId]['status'] ?? null;

    expect($result->output)->toContain('this project loads from outside its root')
        ->and($result->output)->toContain('so edges can be safely refreshed after changes outside the project root')
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($stored)->toBe(0, $result->describe());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a run without tia stores no result when git cannot read the working tree of a project that loads from outside its root', function (): void {
    [$project, $nested] = tiaMonorepo();

    $manifest = json_decode((string) file_get_contents($nested.'/composer.json'), true);
    $manifest['autoload']['psr-4']['Shared\\'] = '../packages/shared/src';
    $project->write('nested/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");
    $project->git()->commit('let the project load a sibling package');
    $project->seedFor($nested, 'master', failing: ['adds two numbers']);

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 2;\n");
    file_put_contents($project->path('.git/index'), 'not an index');
    $project->snapshot();

    $result = $project->pestIn($nested);
    $delta = $project->delta();

    expect($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($delta->writtenCount())->toBe(0, $delta->summary());
})->skipOnWindows();

test('an edit outside the project during the run records no result', function (): void {
    [$project, $nested] = tiaMonorepo();

    $manifest = json_decode((string) file_get_contents($nested.'/composer.json'), true);
    $manifest['autoload']['psr-4']['Shared\\'] = '../packages/shared/src';
    $project->write('nested/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");
    $project->write('nested/tests/Unit/SharedEditTest.php', <<<'PHP_WRAP'
<?php

declare(strict_types=1);

test('edits the shared package', function (): void {
    file_put_contents(dirname(__DIR__, 3).'/packages/shared/src/Shared.php', "<?php\n\n\$shared = 2;\n");

    expect(true)->toBeTrue();
});
PHP_WRAP);
    $project->git()->commit('let the project load a sibling package');
    $project->seedFor($nested, 'master', failing: ['adds two numbers']);
    $project->snapshot();

    $result = $project->pestWithEnvironment($nested, ['XDEBUG_MODE' => 'off'], '--tia');
    $delta = $project->delta();

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and((string) file_get_contents($project->path('packages/shared/src/Shared.php')))->toContain('$shared = 2;')
        ->and($delta->writtenCount())->toBe(0, $delta->summary());
})->skipOnWindows();

test('an edit outside the project during the run keeps the recorded commit', function (): void {
    [$project, $nested] = tiaMonorepo();

    $manifest = json_decode((string) file_get_contents($nested.'/composer.json'), true);
    $manifest['autoload']['psr-4']['Shared\\'] = '../packages/shared/src';
    $project->write('nested/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

    $project->write('packages/shared/src/Shared.php', "<?php\n\n\$shared = 1;\n");
    $project->write('nested/tests/Unit/SharedEditTest.php', <<<'PHP_WRAP'
<?php

declare(strict_types=1);

test('edits the shared package', function (): void {
    file_put_contents(dirname(__DIR__, 3).'/packages/shared/src/Shared.php', "<?php\n\n\$shared = 2;\n");

    expect(true)->toBeTrue();
});
PHP_WRAP);
    $project->git()->commit('let the project load a sibling package');
    $project->seedFor($nested, 'master');
    $recorded = $project->git()->sha();

    $project->write('nested/app/Greeter.php', file_get_contents($nested.'/app/Greeter.php')."\n");
    $project->git()->commit('touch the greeter');

    $result = $project->pestWithEnvironment($nested, ['XDEBUG_MODE' => 'off'], '--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('changed during the run — keeping the baseline from before the run')
        ->and($project->graph()['baselines']['master']['sha'] ?? null)->toBe($recorded);
})->skipOnWindows();

test('a run without tia stores no result when a hook leaves the configuration directory and the phpunit bootstrap outside the project is dirty', function (): void {
    [$project, $nested] = tiaMonorepo();

    $project->write('packages/shared/bootstrap.php', "<?php\n\nrequire __DIR__.'/../../nested/vendor/autoload.php';\n");
    $project->write('nested/phpunit.xml', str_replace(
        'bootstrap="vendor/autoload.php"',
        'bootstrap="../packages/shared/bootstrap.php"',
        (string) file_get_contents($nested.'/phpunit.xml'),
    ));
    $project->write('nested/tests/Unit/LeavesDirectoryTest.php', <<<'PHP_WRAP'
<?php

declare(strict_types=1);

afterAll(function (): void {
    chdir(dirname(__DIR__, 2).'/app');
});

test('leaves the configuration directory', function (): void {
    expect(true)->toBeTrue();
});
PHP_WRAP);
    $project->git()->commit('point the project at a sibling bootstrap file');
    $project->seedFor($nested, 'master', failing: ['adds two numbers']);

    $project->write('packages/shared/bootstrap.php', "<?php\n\nrequire __DIR__.'/../../nested/vendor/autoload.php';\n\n\$booted = 2;\n");
    $project->snapshot();

    $result = $project->pestIn($nested);
    $delta = $project->delta();

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($delta->writtenCount())->toBe(0, $delta->summary());
})->skipOnWindows();
