<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

/*
 * A test that triggers a notice, deprecation or warning is reported by PHPUnit
 * as passed, and emits Passed. Recording it as a plain success made the cache
 * hide the issue: a later run under --fail-on-* came back green where a fresh
 * run failed. Invariant 3 — replay is faithful — at its most dangerous.
 */

function tiaTriggering(string $call): string
{
    return <<<PHP
    <?php

    declare(strict_types=1);

    use Fixture\App\Calculator;

    test('adds two numbers', function (): void {
        {$call}
        expect((new Calculator)->add(1, 2))->toBe(3);
    });

    test('subtracts two numbers', function (): void {
        expect((new Calculator)->subtract(3, 1))->toBe(2);
    });
    PHP;
}

test('a triggered issue is recorded as itself, not as a pass', function (array $arguments, string $call, int $status): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/CalculatorTest.php', tiaTriggering($call));
    $project->git()->commit('trigger an issue');

    $project->pest('--tia', ...$arguments);

    $id = Project::testId('tests/Unit/CalculatorTest.php', 'adds two numbers');

    expect($project->graph()['baselines']['master']['results'][$id]['status'])->toBe($status);
})->with(Project::SEQUENTIAL_AND_PARALLEL)->with([
    'deprecation' => ["trigger_error('legacy adder', E_USER_DEPRECATED);", 4],
    'notice' => ["trigger_error('a notice', E_USER_NOTICE);", 3],
    'warning' => ["trigger_error('a warning', E_USER_WARNING);", 6],
])->skipOnWindows();

test('a cached deprecation still fails the run that asked to fail on one', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/CalculatorTest.php', tiaTriggering("trigger_error('legacy adder', E_USER_DEPRECATED);"));
    $project->git()->commit('trigger a deprecation');

    $project->pest('--tia', ...$arguments);

    $result = $project->pest('--tia', '--fail-on-deprecation', ...$arguments);

    expect($result->exitCode)->toBe(1, $result->describe())
        ->and($result->tally())->toContain('1 deprecated')
        ->and($result->uncached())->toBe(1, $result->describe());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('replaying a cached issue does not downgrade it to a pass', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/CalculatorTest.php', tiaTriggering("trigger_error('legacy adder', E_USER_DEPRECATED);"));
    $project->git()->commit('trigger a deprecation');

    $project->pest('--tia', ...$arguments);

    $id = Project::testId('tests/Unit/CalculatorTest.php', 'adds two numbers');

    foreach (range(1, 3) as $ignored) {
        $project->pest('--tia', ...$arguments);

        expect($project->graph()['baselines']['master']['results'][$id]['status'])->toBe(4);
    }

    $result = $project->pest('--tia', '--fail-on-deprecation', ...$arguments);

    expect($result->exitCode)->toBe(1, $result->describe());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a failure outranks an issue triggered on the way to it', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/CalculatorTest.php', str_replace(
        'toBe(3)',
        'toBe(999)',
        tiaTriggering("trigger_error('legacy adder', E_USER_DEPRECATED);"),
    ));
    $project->git()->commit('an issue then a failure');

    $result = $project->pest('--tia');

    $id = Project::testId('tests/Unit/CalculatorTest.php', 'adds two numbers');

    expect($result->exitCode)->not->toBe(0)
        ->and($project->graph()['baselines']['master']['results'][$id]['status'])->toBe(7);
})->skipOnWindows();

test('a skip outranks an issue triggered on the way to it', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/CalculatorTest.php', str_replace(
        'expect((new Calculator)->add(1, 2))->toBe(3);',
        "\$this->markTestSkipped('not today');",
        tiaTriggering("trigger_error('legacy adder', E_USER_DEPRECATED);"),
    ));
    $project->git()->commit('an issue then a skip');

    $project->pest('--tia');

    $id = Project::testId('tests/Unit/CalculatorTest.php', 'adds two numbers');

    expect($project->graph()['baselines']['master']['results'][$id]['status'])->toBe(1);
})->skipOnWindows();

test('a suppressed issue is not recorded', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/CalculatorTest.php', tiaTriggering("@trigger_error('quiet', E_USER_DEPRECATED);"));
    $project->git()->commit('a suppressed deprecation');

    $project->pest('--tia');

    $id = Project::testId('tests/Unit/CalculatorTest.php', 'adds two numbers');

    expect($project->graph()['baselines']['master']['results'][$id]['status'])->toBe(0);
})->skipOnWindows();
