<?php

use Symfony\Component\Process\Process;

test('visual snapshot of test suite on success', function (): void {
    $snapshot = __DIR__.'/../.snapshots/success.txt';

    $process = new Process(
        ['php', 'bin/pest', 'tests/Fixtures/Suites/SuccessfulRun'],
        dirname(__DIR__, 2),
        ['REBUILD_SNAPSHOTS' => false, 'PARATEST' => 0, 'COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1'],
    );
    $process->setTimeout(300.0);
    $process->mustRun();

    $output = preg_replace('/Duration: \d+\.\d+s/', 'Duration: x.xxs', (string) removeAnsiEscapeSequences($process->getOutput()));
    $output = rtrim((string) $output, "\n")."\n";

    if (getenv('REBUILD_SNAPSHOTS')) {
        expect(file_put_contents($snapshot, $output))->toBe(strlen($output));

        return;
    }

    expect($output)->toBe(file_get_contents($snapshot));
})->skipOnWindows();
