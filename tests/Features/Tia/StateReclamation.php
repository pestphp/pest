<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('a detached HEAD can rebuild the graph on structural drift', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->detach();

    $project->write('composer.lock', (string) json_encode([
        'content-hash' => 'drifted',
        'packages' => [],
        'packages-dev' => [],
    ]));

    $result = $project->pest('--tia', ...$arguments);
    $delta = $project->delta();

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($project->graphExists())->toBeTrue('the detached run deleted graph.json')
        ->and($delta->structureMoved())->toBeTrue($delta->summary());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a detached HEAD can rebuild the graph with --fresh', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->detach();

    $result = $project->pest('--tia', '--fresh', ...$arguments);
    $delta = $project->delta();

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($project->graphExists())->toBeTrue('the detached --fresh run deleted graph.json')
        ->and($delta->structureMoved())->toBeTrue($delta->summary());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a detached HEAD can replace an unreadable graph for a checkout that can rebuild it', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->detach();

    file_put_contents($project->graphDir().'/graph.json', '{not json');

    $result = $project->pest('--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and(file_get_contents($project->graphDir().'/graph.json'))->not->toBe('{not json');
})->skipOnWindows();

test('a cached failure whose test file was deleted stops widening later runs', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master', failing: ['adds two numbers']);

    unlink($project->path('tests/Unit/CalculatorTest.php'));
    $project->git()->commit('drop CalculatorTest');

    $project->pest('--tia', '--filtered', ...$arguments);

    $project->snapshot();
    $second = $project->pest('--tia', '--filtered', ...$arguments);
    $delta = $project->delta();

    expect($second->output)->not->toContain('could not be located on disk')
        ->and($second->output)->toContain('No affected tests found')
        ->and($delta->isHardSuppressed())->toBeTrue($delta->summary());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a complete run reclaims the entry and the edge of a deleted test file', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master', failing: ['adds two numbers']);

    unlink($project->path('tests/Unit/CalculatorTest.php'));
    $project->git()->commit('drop CalculatorTest');

    $project->pest('--tia', ...$arguments);

    $graph = $project->graph();

    expect($graph['edges'] ?? [])->not->toHaveKey('tests/Unit/CalculatorTest.php')
        ->and(array_column($graph['baselines']['master']['results'] ?? [], 'file'))
        ->not->toContain('tests/Unit/CalculatorTest.php');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a pruned result does not come back from the fallback', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master', failing: ['adds two numbers']);

    $project->git()->switchTo('feature-x', new: true);

    $project->write('tests/Unit/CalculatorTest.php', <<<'PHP'
    <?php

    declare(strict_types=1);

    use Fixture\App\Calculator;

    test('adds two numbers, renamed', function (): void {
        expect((new Calculator)->add(1, 2))->toBe(3);
    });

    test('subtracts two numbers', function (): void {
        expect((new Calculator)->subtract(3, 1))->toBe(2);
    });
    PHP);

    $project->git()->commit('rename the test on the branch');

    $project->pest('--tia', '--filtered', ...$arguments);

    $second = $project->pest('--tia', '--filtered', ...$arguments);

    expect($second->output)->not->toContain('previously unsuccessful')
        ->and($second->output)->toContain('No affected tests found');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('the fallback still reaches a branch that has never run a test file', function (): void {
    $project = Project::make('master');
    $project->seed('master', failing: ['adds two numbers']);

    $project->git()->switchTo('feature-x', new: true);

    $project->pest('--filter=greets a person');

    $result = $project->pest('--tia', '--filtered');

    expect($result->output)->toContain('previously unsuccessful')
        ->and($result->affected())->toBe(2, $result->describe());
})->skipOnWindows();

test('a branch that git no longer knows loses its baseline', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);
    $project->pest('--tia');

    expect($project->branchKeys())->toBe(['master', 'feature-x']);

    $project->git()->switchTo('master');
    $project->git()->run(['branch', '-D', 'feature-x']);

    $project->pest('--tia');

    expect($project->branchKeys())->toBe(['master']);
})->skipOnWindows();

test('an unknown cached status is re-run rather than replayed as a failure', function (int $status): void {
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
})->with(['unknown' => -1, 'future' => 9, 'garbage' => 99])->skipOnWindows();

test('a cached notice, deprecation or warning does not replay as a failure', function (int $status): void {
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

test('a malformed baseline entry cannot break the run', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->mutateGraph(function (array $graph): array {
        $id = Project::testId('tests/Unit/CalculatorTest.php', 'adds two numbers');
        $graph['baselines']['master']['results'][$id] = 'nope';
        $graph['baselines']['master']['tree'] = 'nope';
        $graph['edges']['tests/Unit/GreeterTest.php'] = 'nope';

        return $graph;
    });

    $result = $project->pest('--tia', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->not->toContain('TypeError')
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a run torn down mid-file does not prune the tests it never reached', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/CalculatorTest.php', <<<'PHP'
    <?php

    declare(strict_types=1);

    use Fixture\App\Calculator;

    test('adds two numbers', function (): void {
        expect((new Calculator)->add(1, 2))->toBe(3);
    });

    test('subtracts two numbers', function (): void {
        exit(0);
    });
    PHP);

    $project->git()->commit('a test that kills its own process');

    $project->pest('--tia', ...$arguments);
    $delta = $project->delta();

    expect($delta->removed())->toBe(0, $delta->summary())
        ->and($delta->shaMoved())->toBeFalse($delta->summary())
        ->and($delta->structureMoved())->toBeFalse($delta->summary())
        ->and(array_keys($project->graph()['baselines']['master']['results']))
        ->toContain(Project::testId('tests/Unit/CalculatorTest.php', 'subtracts two numbers'));
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a fatal error mid-file is a test error, not a truncation', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/CalculatorTest.php', <<<'PHP'
    <?php

    declare(strict_types=1);

    use Fixture\App\Calculator;

    test('adds two numbers', function (): void {
        expect((new Calculator)->add(1, 2))->toBe(3);
    });

    test('subtracts two numbers', function (): void {
        undefined_function_here();
    });
    PHP);

    $project->git()->commit('a test that fatals');

    $result = $project->pest('--tia', ...$arguments);
    $delta = $project->delta();

    expect($result->exitCode)->not->toBe(0)
        ->and($result->tally())->toContain('1 failed')
        ->and($delta->removed())->toBe(0, $delta->summary())
        ->and($delta->structureMoved())->toBeFalse($delta->summary());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a green complete run leaves the graph exactly as it found it', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--tia', ...$arguments);
    $delta = $project->delta();

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($delta->isHardSuppressed())->toBeTrue($delta->summary());
})->with([
    'bail' => [['--bail']],
    'stop-on-failure' => [['--stop-on-failure']],
    'compact' => [['--compact']],
    'parallel bail' => [['--parallel', '--processes=2', '--bail']],
    'parallel one process' => [['--parallel', '--processes=1']],
    'parallel more processes than files' => [['--parallel', '--processes=8']],
])->skipOnWindows();

test('--tia --no-tia is a plain run that still refreshes what it executed', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--tia', '--no-tia', ...$arguments);
    $delta = $project->delta();

    expect($result->replayed())->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($delta->writtenCount())->toBe(Project::TOTAL_TESTS, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('--fresh on a partial run neither purges nor prunes', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $result = $project->pest('--tia', '--fresh', '--filter=adds two numbers', ...$arguments);
    $delta = $project->delta();

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($project->graphExists())->toBeTrue()
        ->and($delta->writtenCount())->toBe(1, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a second green run on a feature branch writes nothing at all', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);
    $project->pest('--tia', ...$arguments);

    $project->snapshot();
    $result = $project->pest('--tia', ...$arguments);
    $delta = $project->delta();

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($delta->isHardSuppressed())->toBeTrue($delta->summary());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a graph whose recorded commit is gone is re-anchored, not warned about forever', function (array $arguments): void {
    $project = Project::make('master');
    $project->git()->commit('second');
    $project->seed('master');

    $recordedSha = $project->graph()['baselines']['master']['sha'];

    $project->git()->run(['reset', '--quiet', '--hard', 'HEAD~1']);
    $project->snapshot();

    $first = $project->pest('--tia', ...$arguments);

    expect($first->exitCode)->toBe(0, $first->describe())
        ->and($first->output)->toContain('no longer reachable')
        ->and($first->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($project->graph()['baselines']['master']['sha'])->not->toBe($recordedSha)
        ->and($project->graph()['baselines']['master']['sha'])->toBe($project->git()->sha());

    $project->snapshot();
    $second = $project->pest('--tia', ...$arguments);
    $delta = $project->delta();

    expect($second->output)->not->toContain('no longer reachable')
        ->and($second->replayed())->toBe(Project::TOTAL_TESTS, $second->describe())
        ->and($delta->writtenCount())->toBe(0, $delta->summary());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a full suite run without a coverage driver clears the tree it could not refresh', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->mutateGraph(function (array $graph): array {
        $graph['baselines']['master']['tree'] = ['app/Calculator.php' => 'deadbeefdeadbeefdeadbeefdeadbeef'];

        return $graph;
    });

    $environment = ['XDEBUG_MODE' => 'off'];

    $first = $project->pestWithEnvironment($project->path(), $environment, '--tia', ...$arguments);

    expect($first->exitCode)->toBe(0, $first->describe())
        ->and($first->output)->toContain('no coverage driver is available')
        ->and($first->tally())->toContain(Project::TOTAL_TESTS.' passed');

    $project->snapshot();
    $second = $project->pestWithEnvironment($project->path(), $environment, '--tia', ...$arguments);
    $delta = $project->delta();

    expect($second->output)->not->toContain('no coverage driver is available')
        ->and($second->replayed())->toBe(Project::TOTAL_TESTS, $second->describe())
        ->and($delta->writtenCount())->toBe(0, $delta->summary());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();
