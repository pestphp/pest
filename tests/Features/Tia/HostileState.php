<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

/*
 * Invariant 7 — a hostile state dir cannot break a run. Whatever is in
 * graph.json, the suite still runs and exits on the tests' merit.
 */

test('a graph mangled beyond use still lets the suite run', function (string $contents): void {
    $project = Project::make('master');
    $project->seed('master');

    file_put_contents($project->graphDir().'/graph.json', $contents);

    $result = $project->pest('--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->with([
    'empty' => '',
    'truncated' => '{"schema":1,"files":["app/Calculator.php"],"edg',
    'not json' => '{not json',
    'json scalar' => '"just a string"',
    'json list' => '[1,2,3]',
    'json null' => 'null',
    'empty object' => '{}',
    'nul bytes' => "\0\0\0\0",
])->skipOnWindows();

test('a graph whose shape is wrong everywhere is repaired rather than trusted', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->mutateGraph(function (array $graph): array {
        $id = Project::testId('tests/Unit/CalculatorTest.php', 'adds two numbers');

        $graph['baselines']['master']['results'][$id] = 'nope';
        $graph['baselines']['master']['results'][7] = ['status' => 0, 'message' => '', 'time' => 0.1];
        $graph['baselines']['master']['tree'] = 'nope';
        $graph['baselines']['master']['sha'] = 42;
        $graph['baselines'][''] = ['sha' => null, 'tree' => [], 'results' => []];
        $graph['baselines']['broken'] = 'nope';
        $graph['edges']['tests/Unit/GreeterTest.php'] = 'nope';
        $graph['edges'][''] = [0];
        $graph['files'][] = ['nested'];

        return $graph;
    });

    $result = $project->pest('--tia', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->not->toContain('TypeError')
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($project->branchKeys())->toBe(['master']);
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a cached status this build cannot interpret is re-run, not replayed', function (int $status): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->mutateGraph(function (array $graph) use ($status): array {
        $id = Project::testId('tests/Unit/CalculatorTest.php', 'adds two numbers');
        $graph['baselines']['master']['results'][$id]['status'] = $status;

        return $graph;
    });

    $result = $project->pest('--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($result->uncached())->toBe(1, $result->describe());
})->with([
    'below the range' => -1,
    'one past the range' => 9,
    'far past the range' => 99,
    'huge' => PHP_INT_MAX,
])->skipOnWindows();

test('a cached status with no replay of its own does not fail the run', function (int $status): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->mutateGraph(function (array $graph) use ($status): array {
        $id = Project::testId('tests/Unit/CalculatorTest.php', 'adds two numbers');
        $graph['baselines']['master']['results'][$id]['status'] = $status;
        $graph['baselines']['master']['results'][$id]['message'] = 'cached detail';

        return $graph;
    });

    $result = $project->pest('--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->with(['notice' => 3, 'deprecation' => 4, 'warning' => 6])->skipOnWindows();

test('a cached skip or todo replays with its message intact', function (int $status, string $tally): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->mutateGraph(function (array $graph) use ($status): array {
        $id = Project::testId('tests/Unit/CalculatorTest.php', 'adds two numbers');
        $graph['baselines']['master']['results'][$id]['status'] = $status;
        $graph['baselines']['master']['results'][$id]['message'] = 'a recorded reason';

        return $graph;
    });

    $result = $project->pest('--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain($tally)
        ->and($result->output)->toContain('a recorded reason')
        ->and($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe());
})->with([
    'skipped' => [1, '1 skipped'],
    'incomplete' => [2, '1 incomplete'],
])->skipOnWindows();

test('a cached failure with a multi-line message re-runs rather than replaying the text', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->mutateGraph(function (array $graph): array {
        $id = Project::testId('tests/Unit/CalculatorTest.php', 'adds two numbers');
        $graph['baselines']['master']['results'][$id]['status'] = 7;
        $graph['baselines']['master']['results'][$id]['message'] = "line one\nline two\nline three";

        return $graph;
    });

    $result = $project->pest('--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->uncached())->toBe(1, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->skipOnWindows();

test('a result pointing outside the project is not addressable and widens the run', function (): void {
    $project = Project::make('master');
    $project->seed('master', failing: ['adds two numbers']);

    $project->mutateGraph(function (array $graph): array {
        $id = Project::testId('tests/Unit/CalculatorTest.php', 'adds two numbers');
        $graph['baselines']['master']['results'][$id]['file'] = '/build/agent/tests/Unit/CalculatorTest.php';

        return $graph;
    });

    $result = $project->pest('--tia', '--filtered');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('could not be located on disk')
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->skipOnWindows();

test('an edge pointing at a file id that does not exist is ignored', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->mutateGraph(function (array $graph): array {
        $graph['edges']['tests/Unit/CalculatorTest.php'] = [0, 999, -5];

        return $graph;
    });

    $result = $project->pest('--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe());
})->skipOnWindows();

test('a graph from a schema this build does not know is rebuilt, not read', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->mutateGraph(function (array $graph): array {
        $graph['schema'] = 2;

        return $graph;
    });

    $result = $project->pest('--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($result->replayed())->toBe(0, $result->describe());
})->skipOnWindows();

test('graph.json being a directory does not stop the run', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    unlink($project->graphDir().'/graph.json');
    mkdir($project->graphDir().'/graph.json');

    $result = $project->pest('--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->skipOnWindows();

test('a state dir it cannot write to still replays', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    chmod($project->graphDir(), 0500);

    try {
        $result = $project->pest('--tia');
    } finally {
        chmod($project->graphDir(), 0700);
    }

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe());
})->skipOnWindows();
