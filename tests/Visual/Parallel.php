<?php

use Symfony\Component\Process\Process;

$run = function () {
    $process = new Process(
        array_merge(['php', 'bin/pest', '--parallel', '--processes=3'], func_get_args()),
        dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true'],
    );

    $process->run();

    return removeAnsiEscapeSequences($process->getOutput());
};

test('parallel', function () use ($run) {
    expect($run('--exclude-group=integration'))
        ->toContain('Tests:    2 deprecated, 4 warnings, 5 incomplete, 2 notices, 39 todos, 26 skipped, 1178 passed (2790 assertions)')
        ->toContain('Parallel: 3 processes');
})->skipOnWindows();

test('a parallel test can extend another test with same name', function () use ($run) {
    expect($run('tests/Fixtures/Inheritance'))->toContain('Tests:    1 skipped, 2 passed (2 assertions)');
});

test('parallel mode respects --colors=never option', function () {
    $process = new Process([
        'php',
        './bin/pest',
        '--parallel',
        '--processes=2',
        '--colors=never',
        'tests/Fixtures/DirectoryWithTests/ExampleTest.php',
    ], dirname(__DIR__, 2), ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true']);
    $process->run();
    $output = $process->getOutput();

    // Output should not contain ANSI color codes when --colors=never is used
    expect($output)
        ->not->toMatch('/\x1b\[[0-9;]*m/')  // ANSI color codes pattern
        ->toContain('Tests:')               // Should still have test output
        ->toContain('Parallel:');           // Should still indicate parallel mode
})->skipOnWindows();
