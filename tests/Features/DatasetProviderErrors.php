<?php

use Symfony\Component\Process\Process;

$run = function (string $target): array {
    $process = new Process(
        ['php', 'bin/pest', $target],
        dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true'],
    );

    $process->run();

    return [
        'output' => removeAnsiEscapeSequences($process->getOutput().$process->getErrorOutput()),
        'code' => $process->getExitCode(),
    ];
};

test('reports missing datasets as errors for a single file run', function () use ($run) {
    $result = $run('tests/.tests/IssueOnly.php');

    expect($result['output'])
        ->toContain("A dataset with the name `missing` does not exist. You can create it using `dataset('missing', ['a', 'b']);`.")
        ->toContain('FAILED')
        ->toContain('Tests:    1 failed');

    expect($result['code'])->not->toBe(0);
})->skipOnWindows();

test('reports missing datasets as errors alongside passing tests', function () use ($run) {
    $result = $run('tests/.tests/IssueWithPassing.php');

    expect($result['output'])
        ->toContain("A dataset with the name `missing` does not exist. You can create it using `dataset('missing', ['a', 'b']);`.")
        ->toContain('1 passed')
        ->toContain('1 failed');

    expect($result['code'])->not->toBe(0);
})->skipOnWindows();

test('reports dataset closure exceptions as errors', function () use ($run) {
    $result = $run('tests/.tests/DatasetClosureThrows.php');

    expect($result['output'])
        ->toContain('boom from dataset');

    expect($result['code'])->not->toBe(0);
})->skipOnWindows();
