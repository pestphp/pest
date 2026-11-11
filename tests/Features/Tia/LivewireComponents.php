<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

function tiaLivewireHash(string $sourcePath): string
{
    return substr(md5(DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $sourcePath)), 0, 8);
}

/**
 * @param  array<string, string>  $generatedToTest  generated path → test file that rendered it
 */
function tiaSeedWithGeneratedViews(Project $project, array $generatedToTest): void
{
    $project->seed('master');

    $project->mutateGraph(function (array $graph) use ($generatedToTest): array {
        foreach ($generatedToTest as $generated => $testFile) {
            $id = count($graph['files']);
            $graph['files'][$id] = $generated;
            $graph['edges'][$testFile][] = $id;
        }

        return $graph;
    });
}

test('a changed single-file component selects only the tests that rendered it, across workers', function (): void {
    $project = Project::make('master', 'livewire-watch');
    $project->write('resources/views/pages/⚡orders.blade.php', "<div>orders</div>\n");
    $project->git()->commit('add the component');

    $hash = tiaLivewireHash('resources/views/pages/⚡orders.blade.php');

    tiaSeedWithGeneratedViews($project, [
        'storage/framework/views/test_1/livewire/views/'.$hash.'.blade.php' => 'tests/Unit/GreeterTest.php',
        'storage/framework/views/test_2/livewire/views/'.$hash.'.blade.php' => 'tests/Unit/CalculatorTest.php',
    ]);

    $project->write('resources/views/pages/⚡orders.blade.php', "<div>orders v2</div>\n");
    $project->snapshot();

    $result = $project->pest('--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->affected())->toBe(4, $result->describe())
        ->and($result->replayed())->toBe(2, $result->describe());
})->skipOnWindows();

test('a changed multi-file component asset selects the tests that rendered the component', function (): void {
    $project = Project::make('master', 'livewire-watch');
    $project->write('resources/views/components/⚡counter/counter.blade.php', "<div>{{ \$count }}</div>\n");
    $project->write('resources/views/components/⚡counter/counter.php', "<?php\n\nreturn 1;\n");
    $project->write('resources/views/components/⚡counter/counter.js', "export default 1\n");
    $project->git()->commit('add the component');

    tiaSeedWithGeneratedViews($project, [
        'storage/framework/views/livewire/classes/'.tiaLivewireHash('resources/views/components/⚡counter').'.php' => 'tests/Unit/GreeterTest.php',
    ]);

    $project->write('resources/views/components/⚡counter/counter.js', "export default 2\n");
    $project->snapshot();

    $result = $project->pest('--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->affected())->toBe(2, $result->describe())
        ->and($result->replayed())->toBe(4, $result->describe());
})->skipOnWindows();

test('a deleted single-file component selects the tests that rendered it', function (): void {
    $project = Project::make('master', 'livewire-watch');
    $project->write('resources/views/pages/⚡orders.blade.php', "<div>orders</div>\n");
    $project->git()->commit('add the component');

    tiaSeedWithGeneratedViews($project, [
        'storage/framework/views/livewire/views/'.tiaLivewireHash('resources/views/pages/⚡orders.blade.php').'.blade.php' => 'tests/Unit/GreeterTest.php',
    ]);

    unlink($project->path('resources/views/pages/⚡orders.blade.php'));
    $project->snapshot();

    $result = $project->pest('--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->affected())->toBe(2, $result->describe())
        ->and($result->replayed())->toBe(4, $result->describe());
})->skipOnWindows();

test('a Blade file with no generated view still falls back to the watch pattern', function (): void {
    $project = Project::make('master', 'livewire-watch');
    $project->write('resources/views/pages/⚡orders.blade.php', "<div>orders</div>\n");
    $project->write('resources/views/unrelated.blade.php', "<div>unrelated</div>\n");
    $project->git()->commit('add the views');

    tiaSeedWithGeneratedViews($project, [
        'storage/framework/views/livewire/views/'.tiaLivewireHash('resources/views/pages/⚡orders.blade.php').'.blade.php' => 'tests/Unit/GreeterTest.php',
    ]);

    $project->write('resources/views/unrelated.blade.php', "<div>unrelated v2</div>\n");
    $project->snapshot();

    $result = $project->pest('--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->affected())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();
