<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

/**
 * The selection paths below are all driven from a *seeded* graph rather than a
 * recorded one: Blade and Inertia edges are recorded through Laravel hooks the
 * fixture project does not have, and a changed `.php` source file would trip
 * the driverless full-suite fallback. Views and JS files are neither, so what
 * `Graph::affected()` does with them is measurable on any interpreter.
 */
function tiaSeedWithView(Project $project, string $view): void
{
    $project->seed('master');

    $project->mutateGraph(function (array $graph) use ($view): array {
        $id = count($graph['files']);
        $graph['files'][$id] = $view;
        $graph['edges']['tests/Unit/GreeterTest.php'][] = $id;

        return $graph;
    });
}

/**
 * @param  array<int, string>  $components
 * @param  array<string, array<int, string>>  $jsFileToComponents
 */
function tiaSeedWithInertia(Project $project, array $components, array $jsFileToComponents = []): void
{
    $project->seed('master');

    $project->mutateGraph(function (array $graph) use ($components, $jsFileToComponents): array {
        $graph['test_inertia_components'] = ['tests/Unit/GreeterTest.php' => $components];
        $graph['js_file_to_components'] = $jsFileToComponents;

        return $graph;
    });
}

test('a committed rename selects the tests that depended on the old path', function (array $arguments): void {
    $project = Project::make('master');
    $project->write('resources/views/greeting.blade.php', "<p>Hello</p>\n");
    $project->git()->commit('add view');

    tiaSeedWithView($project, 'resources/views/greeting.blade.php');

    // git reports only the destination of a rename unless asked not to, so the
    // path the graph holds an edge for is the one that must still show up.
    $project->git()->run(['mv', 'resources/views/greeting.blade.php', 'resources/views/hello.blade.php']);
    $project->git()->commit('move the view');
    $project->snapshot();

    $result = $project->pest('--tia', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->affected())->toBe(2, $result->describe())
        ->and($result->replayed())->toBe(4, $result->describe());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('an affected test file that is gone does not strand a filtered run', function (array $arguments): void {
    $project = Project::make('master');
    $project->write('resources/views/page.blade.php', "<p>one</p>\n");
    $project->git()->commit('add view');
    $project->seed('master');

    // A graph written before this checkout existed — a fetched baseline, or a
    // branch that deleted the file — can hold an edge for a test file nothing
    // can run. Selecting it would filter the suite down to nothing and report
    // success on a change no test looked at.
    $project->mutateGraph(function (array $graph): array {
        $id = count($graph['files']);
        $graph['files'][$id] = 'resources/views/page.blade.php';
        $graph['edges']['tests/Unit/GhostTest.php'] = [$id];

        return $graph;
    });

    $project->write('resources/views/page.blade.php', "<p>two</p>\n");
    $project->snapshot();

    $result = $project->pest('--tia', '--filtered', ...$arguments);
    $delta = $project->delta();

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('No affected tests found')
        ->and($result->output)->not->toContain('tests/Unit/GhostTest.php')
        ->and($delta->isHardSuppressed())->toBeTrue($delta->summary());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a plain run reclaims the edge of a test file that is gone', function (): void {
    $project = Project::make('master');
    $project->write('resources/views/page.blade.php', "<p>one</p>\n");
    $project->git()->commit('add view');
    $project->seed('master');

    $project->mutateGraph(function (array $graph): array {
        $id = count($graph['files']);
        $graph['files'][$id] = 'resources/views/page.blade.php';
        $graph['edges']['tests/Unit/GhostTest.php'] = [$id];
        $graph['baselines']['master']['results']['P\\Tests\\Unit\\GhostTest::ghostly'] = [
            'status' => 0,
            'message' => '',
            'time' => 9.999,
            'assertions' => 42,
            'file' => 'tests/Unit/GhostTest.php',
        ];

        return $graph;
    });

    $project->write('resources/views/page.blade.php', "<p>two</p>\n");
    $project->snapshot();

    $result = $project->pest('--tia');
    $delta = $project->delta();

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($delta->removed())->toBe(1, $delta->summary())
        ->and($project->graph()['edges'])->not->toHaveKey('tests/Unit/GhostTest.php');
})->skipOnWindows();

test('a changed view selects the test that rendered it', function (): void {
    $project = Project::make('master');
    $project->write('resources/views/page.blade.php', "<p>one</p>\n");
    $project->git()->commit('add view');

    tiaSeedWithView($project, 'resources/views/page.blade.php');

    $project->write('resources/views/page.blade.php', "<p>two</p>\n");
    $project->snapshot();

    $result = $project->pest('--tia');

    expect($result->affected())->toBe(2, $result->describe())
        ->and($result->replayed())->toBe(4, $result->describe());
})->skipOnWindows();

test('a changed partial selects the test that rendered its ancestor', function (array $views, string $changed): void {
    $project = Project::make('master');

    foreach ($views as $path => $contents) {
        $project->write($path, $contents);
    }

    $project->git()->commit('add views');

    tiaSeedWithView($project, 'resources/views/page.blade.php');

    $project->write($changed, $views[$changed]."<span>edited</span>\n");
    $project->snapshot();

    $result = $project->pest('--tia');

    expect($result->affected())->toBe(2, $result->describe())
        ->and($result->replayed())->toBe(4, $result->describe());
})->with([
    'direct @include' => [[
        'resources/views/page.blade.php' => "@include('partials.nav')\n",
        'resources/views/partials/nav.blade.php' => "<nav>one</nav>\n",
    ], 'resources/views/partials/nav.blade.php'],
    'transitive @include' => [[
        'resources/views/page.blade.php' => "@include('partials.wrapper')\n",
        'resources/views/partials/wrapper.blade.php' => "@include('partials.nav')\n",
        'resources/views/partials/nav.blade.php' => "<nav>one</nav>\n",
    ], 'resources/views/partials/nav.blade.php'],
    'x- component' => [[
        'resources/views/page.blade.php' => "<x-card>hi</x-card>\n",
        'resources/views/components/card.blade.php' => "<div>one</div>\n",
    ], 'resources/views/components/card.blade.php'],
    // Two partials that include each other: the ancestor walk has to notice it
    // has seen them and stop, rather than chase the cycle forever.
    'include cycle' => [[
        'resources/views/page.blade.php' => "@include('partials.a')\n",
        'resources/views/partials/a.blade.php' => "@include('partials.b')\n",
        'resources/views/partials/b.blade.php' => "@include('partials.a')\n",
    ], 'resources/views/partials/b.blade.php'],
])->skipOnWindows();

test('a changed Inertia page selects the test that rendered its component', function (): void {
    $project = Project::make('master');
    $project->write('resources/js/Pages/Foo.vue', "<template>one</template>\n");
    $project->git()->commit('add page');

    tiaSeedWithInertia($project, ['Foo']);

    $project->write('resources/js/Pages/Foo.vue', "<template>two</template>\n");
    $project->snapshot();

    $result = $project->pest('--tia');

    expect($result->affected())->toBe(2, $result->describe())
        ->and($result->replayed())->toBe(4, $result->describe());
})->skipOnWindows();

test('a changed shared JS module selects the tests of the pages that import it', function (): void {
    $project = Project::make('master');
    $project->write('resources/js/Pages/Foo.vue', "<template>one</template>\n");
    $project->write('resources/js/Shared/Nav.vue', "<template>nav</template>\n");
    $project->git()->commit('add pages');

    tiaSeedWithInertia($project, ['Foo'], ['resources/js/Shared/Nav.vue' => ['Foo']]);

    $project->write('resources/js/Shared/Nav.vue', "<template>nav two</template>\n");
    $project->snapshot();

    $result = $project->pest('--tia');

    expect($result->affected())->toBe(2, $result->describe())
        ->and($result->replayed())->toBe(4, $result->describe());
})->skipOnWindows();

test('a changed frontend runtime file selects every Inertia test', function (): void {
    $project = Project::make('master');
    $project->write('resources/js/app.js', "console.log(1)\n");
    $project->git()->commit('add runtime');

    tiaSeedWithInertia($project, ['Foo']);

    $project->write('resources/js/app.js', "console.log(2)\n");
    $project->snapshot();

    $result = $project->pest('--tia');

    expect($result->affected())->toBe(2, $result->describe())
        ->and($result->replayed())->toBe(4, $result->describe());
})->skipOnWindows();
