<?php

use Symfony\Component\Process\Process;

$run = function (bool $parallel) {
    $process = new Process(
        ['php', 'bin/pest', 'tests/Features/Incompleted.php', $parallel ? '--parallel' : '', '--fail-on-incomplete'],
        dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true'],
    );

    $process->run();

    expect($process->getExitCode())->toBe(1);

    return removeAnsiEscapeSequences($process->getOutput());
};

test('incompleted test with fail-on-incomplete returns non-zero exit code', function () use ($run) {
    expect($run(false))
        ->toContain('Tests:    5 incomplete');
})
    ->skipOnWindows()
    ->skip(! getenv('REBUILD_SNAPSHOTS') && getenv('EXCLUDE'));

test('incompleted test with fail-on-incomplete returns non-zero exit code parallel', function () use ($run) {
    expect($run(true))
        ->toContain('Tests:    5 incomplete');
})
    ->skipOnWindows()
    ->skip(! getenv('REBUILD_SNAPSHOTS') && getenv('EXCLUDE'));
