<?php

declare(strict_types=1);

use Pest\TestsTia\Support\Sandbox;

/*
 * The canonical cycle:
 *   1. Cold `--tia` run → record mode → graph written, tests pass.
 *   2. Subsequent `--tia` runs → replay mode, every test cache-hits.
 */

test('cold run records the graph', function () {
    tiaScenario(function (Sandbox $sandbox) {
        $process = $sandbox->pest(['--tia']);

        expect($process->isSuccessful())->toBeTrue(tiaOutput($process));
        expect(tiaOutput($process))->toContain('recording dependency graph');
        expect($sandbox->hasGraph())->toBeTrue();

        $graph = $sandbox->graph();
        expect($graph)->toHaveKey('edges');
        expect(array_keys($graph['edges']))->toContain('tests/MathTest.php');
        expect(array_keys($graph['edges']))->toContain('tests/GreeterTest.php');
    });
});

test('warm run replays every test', function () {
    tiaScenario(function (Sandbox $sandbox) {
        // Cold pass: records edges AND snapshots results (series mode
        // runs `snapshotTestResults` in the same `addOutput` pass).
        $sandbox->pest(['--tia']);

        $process = $sandbox->pest(['--tia']);

        expect($process->isSuccessful())->toBeTrue(tiaOutput($process));
        // Zero changes → only the `replayed` fragment appears in the
        // recap; the `affected` fragment is omitted when count is 0.
        expect(tiaOutput($process))->toMatch('/3 replayed/');
        expect(tiaOutput($process))->not()->toMatch('/\d+ affected/');
    });
});
