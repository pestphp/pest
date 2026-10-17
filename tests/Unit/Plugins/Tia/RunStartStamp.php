<?php

declare(strict_types=1);

use Pest\Plugins\Tia;
use Pest\Plugins\Tia\Graph;

test('a graph baseline stays anchored to the run-start snapshot', function (): void {
    $tia = new ReflectionClass(Tia::class)->newInstanceWithoutConstructor();
    $startSnapshot = [
        'sha' => str_repeat('a', 40),
        'tree' => ['app/Service.php' => str_repeat('b', 32)],
    ];

    new ReflectionProperty(Tia::class, 'startRunSnapshot')->setValue($tia, $startSnapshot);

    $graph = new Graph(sys_get_temp_dir());
    $graph->setRecordedAtSha('main', str_repeat('c', 40));
    $graph->setLastRunTree('main', ['app/Service.php' => str_repeat('d', 32)]);

    new ReflectionMethod($tia, 'stampRunStart')->invoke($tia, $graph);

    expect($graph->recordedAtSha('main'))->toBe($startSnapshot['sha'])
        ->and($graph->lastRunTree('main'))->toBe($startSnapshot['tree']);
});
