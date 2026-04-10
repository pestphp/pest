<?php

use Symfony\Component\Process\Process;

$run = function (bool $parallel = false): array {
    $command = [
        'php',
        'bin/pest',
        'tests/Fixtures/ParallelNestedDatasets/TestFileWithNestedDataset.php',
        '--colors=never',
    ];

    if ($parallel) {
        $command[] = '--parallel';
        $command[] = '--processes=2';
    }

    $process = new Process($command, dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true'],
    );

    $process->run();

    return [
        'exitCode' => $process->getExitCode(),
        'output' => removeAnsiEscapeSequences($process->getOutput().$process->getErrorOutput()),
    ];
};

test('parallel loads nested datasets from nested directories', function () use ($run) {
    $serial = $run();
    $parallel = $run(true);

    expect($serial['exitCode'])->toBe(0)
        ->and($parallel['exitCode'])->toBe(0)
        ->and($serial['output'])->toContain('Tests:    2 passed (2 assertions)')
        ->and($parallel['output'])->toContain('Tests:    2 passed (2 assertions)')
        ->and($parallel['output'])->toContain('Parallel: 2 processes')
        ->and($parallel['output'])->not->toContain('No tests found.');
})->skipOnWindows();
