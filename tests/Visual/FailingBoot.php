<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

test('a failure in the boot files renders only that failure', function (): void {
    $process = new Process([
        'php',
        'bin/pest',
        '--test-directory=tests/Fixtures/Suites/FailingBoot',
        '--colors=never',
    ], dirname(__DIR__, 2), ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1']);

    $process->run();

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput().$process->getErrorOutput())
        ->toContain('The boot of the test suite failed.')
        ->not->toContain('cannot be resolved');
})->skipOnWindows();
