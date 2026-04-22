<?php

declare(strict_types=1);

use Pest\TestsTia\Support\Sandbox;

/*
 * Fingerprint splits into structural vs environmental. Hand-forge each
 * drift flavour on a valid graph and assert the right branch fires.
 */

test('structural drift discards the graph entirely', function () {
    tiaScenario(function (Sandbox $sandbox) {
        $sandbox->pest(['--tia']);

        $graphPath = $sandbox->path().'/vendor/pestphp/pest/.temp/tia/graph.json';
        $graph = json_decode((string) file_get_contents($graphPath), true);
        $graph['fingerprint']['structural']['composer_lock'] = str_repeat('0', 32);
        file_put_contents($graphPath, json_encode($graph));

        $process = $sandbox->pest(['--tia']);

        expect($process->isSuccessful())->toBeTrue(tiaOutput($process));
        expect(tiaOutput($process))->toContain('graph structure outdated');
    });
});

test('environmental drift keeps edges, drops results', function () {
    tiaScenario(function (Sandbox $sandbox) {
        $sandbox->pest(['--tia']);

        $graphPath = $sandbox->path().'/vendor/pestphp/pest/.temp/tia/graph.json';
        $graph = json_decode((string) file_get_contents($graphPath), true);

        $edgeCountBefore = count($graph['edges']);

        $graph['fingerprint']['environmental']['php_minor'] = '7.4';
        $graph['fingerprint']['environmental']['extensions'] = str_repeat('0', 32);
        file_put_contents($graphPath, json_encode($graph));

        $process = $sandbox->pest(['--tia']);

        expect($process->isSuccessful())->toBeTrue(tiaOutput($process));
        expect(tiaOutput($process))->toContain('env differs from baseline');
        expect(tiaOutput($process))->toContain('results dropped, edges reused');

        $graphAfter = $sandbox->graph();
        expect(count($graphAfter['edges']))->toBe($edgeCountBefore);
        expect($graphAfter['fingerprint']['environmental']['php_minor'])
            ->not()->toBe('7.4');
    });
});
