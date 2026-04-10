<?php

use Symfony\Component\Process\Process;

test('filter works with unicode characters in filename', function () {
    $process = new Process([
        'php',
        'bin/pest',
        'tests/.tests/StraßenTest.php',
        '--colors=never',
    ], dirname(__DIR__, 2), ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true']);

    $process->run();

    $output = $process->getOutput();

    expect($output)->toContain('StraßenTest');
    expect($output)->toContain('tests unicode filename');
    expect($output)->toContain('1 passed');
})->skipOnWindows();

test('filter with unicode regex matches unicode filename', function () {
    $process = new Process([
        'php',
        'bin/pest',
        '--filter=.*Straß.*',
        'tests/.tests/',
        '--colors=never',
    ], dirname(__DIR__, 2), ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true']);

    $process->run();

    $output = $process->getOutput();

    expect($output)->toContain('StraßenTest');
    expect($output)->toContain('1 passed');
})->skipOnWindows();
