<?php

declare(strict_types=1);

use Pest\TestsTia\Support\Sandbox;

/*
 * `--tia-rebuild` short-circuits whatever graph is on disk and records
 * from scratch. Used when the user knows the cache is wrong.
 */

test('--tia-rebuild forces record mode even with a valid graph', function () {
    tiaScenario(function (Sandbox $sandbox) {
        $sandbox->pest(['--tia']);
        expect($sandbox->hasGraph())->toBeTrue();

        $graphBefore = $sandbox->graph();

        $process = $sandbox->pest(['--tia', '--tia-rebuild']);

        expect($process->isSuccessful())->toBeTrue(tiaOutput($process));
        expect(tiaOutput($process))->toContain('recording dependency graph');

        $graphAfter = $sandbox->graph();
        expect(array_keys($graphAfter['edges']))
            ->toEqualCanonicalizing(array_keys($graphBefore['edges']));
    });
});
