<?php

use Symfony\Component\Process\Process;

$run = function () {
    $process = new Process(['php', 'bin/pest', '--parallel', '--processes=3', '--exclude-group=integration'], dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true'],
    );

    $process->run();

    expect($process->getExitCode())->toBe(0);

    return preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $process->getOutput());
};

test('parallel', function () use ($run) {
    expect($run())->toContain('Tests:    4 incomplete, 4 todos, 15 skipped, 627 passed (1551 assertions)')
        ->toContain('Parallel: 3 processes');
});
