<?php

use Symfony\Component\Process\Process;

$run = function (string ...$args) {
    $process = new Process(
        array_merge(['php', 'bin/pest', '--parallel', '--processes=2'], $args),
        dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true'],
    );

    $process->run();

    return [
        'output' => removeAnsiEscapeSequences($process->getOutput()),
        'exitCode' => $process->getExitCode(),
    ];
};

test('parallel bootstraps extensions that register subscribers the same way sequential does', function () use ($run) {
    $result = $run('-c', 'tests/.tests/ParallelRunnerWarning/phpunit.xml');

    expect($result['output'])
        ->toContain('2 passed')
        ->toContain('Parallel: 2 processes')
        ->not->toContain('warning')
        ->not->toContain('Bootstrapping of extension');

    expect($result['exitCode'])->toBe(0);
})->skipOnWindows();
