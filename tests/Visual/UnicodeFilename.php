<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

test('filter works with unicode characters in filename', function (): void {
    $process = new Process([
        'php',
        'bin/pest',
        'tests/Fixtures/Suites/StraßenTest.php',
        '--colors=never',
    ], dirname(__DIR__, 2), ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1']);

    $process->run();

    $output = $process->getOutput();

    expect($output)->toContain('StraßenTest')
        ->toContain('tests unicode filename')
        ->toContain('1 passed');
})->skipOnWindows();

test('filter with unicode regex matches unicode filename', function (): void {
    $process = new Process([
        'php',
        'bin/pest',
        '--filter=.*Straß.*',
        'tests/Fixtures/Suites/',
        '--colors=never',
    ], dirname(__DIR__, 2), ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1']);

    $process->run();

    $output = $process->getOutput();

    expect($output)->toContain('StraßenTest')
        ->toContain('1 passed');
})->skipOnWindows();
