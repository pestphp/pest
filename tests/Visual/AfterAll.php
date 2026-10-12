<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

test('afterAll hooks run once after all tests of the file', function (): void {
    $marker = tempnam(sys_get_temp_dir(), 'pest-after-all-');

    $process = new Process([
        'php',
        'bin/pest',
        'tests/Fixtures/Suites/AfterAll',
        '--test-directory=tests/Fixtures/Suites/AfterAll',
        '--colors=never',
    ], dirname(__DIR__, 2), [
        'COLLISION_PRINTER' => 'DefaultPrinter',
        'COLLISION_IGNORE_DURATION' => 'true',
        'PAO_DISABLE' => '1',
        'PEST_AFTER_ALL_MARKER' => $marker,
    ]);

    $process->run();

    $calls = file_get_contents($marker);

    unlink($marker);

    expect($process->getExitCode())->toBe(0)
        ->and($calls)->toBe('pest()->afterAll'.PHP_EOL.'afterAll'.PHP_EOL);
})->skipOnWindows();
