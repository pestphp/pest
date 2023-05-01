<?php

use Symfony\Component\Process\Process;

$run = function () {
    $process = new Process(
        array_merge(['php', 'bin/pest', '--parallel', '--processes=3'], func_get_args()),
        dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true'],
    );

    $process->run();

    // expect($process->getExitCode())->toBe(0);

    return preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $process->getOutput());
};

test('parallel', function () use ($run) {
    expect($run('--exclude-group=integration'))
        ->toContain('Tests:    1 deprecated, 3 warnings, 4 incomplete, 1 notice, 8 todos, 14 skipped, 703 passed (1712 assertions)')
        ->toContain('Parallel: 3 processes');
})->skipOnWindows();

test('a parallel test can extend another test with same name', function () use ($run) {
    expect($run('tests/Fixtures/Inheritance'))->toContain('Tests:    1 skipped, 2 passed (2 assertions)');
});
