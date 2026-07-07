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
    $output = $run('--exclude-group=integration');
    $output = implode("\n", array_slice(explode("\n", $output), -10));

    if (getenv('REBUILD_SNAPSHOTS')) {
        preg_match('/Tests:\s+(.+\(\d+ assertions\))/', $output, $matches);

        $file = file_get_contents(__FILE__);
        $file = preg_replace(
            '/\$expected = \'.*?\';/',
            "\$expected = '2 deprecated, 4 warnings, 5 incomplete, 3 notices, 40 todos, 27 skipped, 1314 passed (2959 assertions)';",
            $file,
        );
        file_put_contents(__FILE__, $file);
    }

    $expected = '2 deprecated, 4 warnings, 5 incomplete, 3 notices, 40 todos, 27 skipped, 1314 passed (2959 assertions)';

    expect($output)
        ->toContain("Tests:    {$expected}")
        ->toContain('Parallel: 3 processes');
})->skipOnWindows();

test('a parallel test can extend another test with same name', function () use ($run) {
    expect($run('tests/Fixtures/Inheritance'))->toContain('Tests:    1 skipped, 1 passed (1 assertions)');
})->skipOnWindows();

test('parallel reports invalid datasets as failures', function () use ($run) {
    expect($run('tests/.tests/ParallelInvalidDataset'))
        ->toContain("A dataset with the name `missing.dataset` does not exist. You can create it using `dataset('missing.dataset', ['a', 'b']);`.")
        ->toContain('Tests:    1 failed, 1 passed (1 assertions)')
        ->toContain('Parallel: 3 processes');
})->skipOnWindows();

test('parallel stop-on-* fixture runs every case without a stop flag', function (string $testName) use ($run) {
    $output = $run('--filter', "($testName|passes)", 'tests/.tests/ParallelStopOn');
    expect($output)->toContain('10 passed');
})->with([
    'fails',
    'errors',
    'is risky',
    'is incomplete',
    'warns',
    'notices',
    'deprecates',
    'is skipped',
])->skipOnWindows();

test('parallel honors --stop-on-*', function (string $flag, string $testName) use ($run) {
    $output = $run('--filter', "($testName|passes)", $flag, 'tests/.tests/ParallelStopOn');
    preg_match('/(\d+) passed/', $output, $matches);
    expect((int) ($matches[1] ?? 0))->toBeLessThan(10);
})->with([
    'failure' => ['--stop-on-failure', 'fails'],
    'defect' => ['--stop-on-defect', 'fails'],
    'error' => ['--stop-on-error', 'errors'],
    'risky' => ['--stop-on-risky', 'is risky'],
    'incomplete' => ['--stop-on-incomplete', 'is incomplete'],
    'warning' => ['--stop-on-warning', 'warns'],
    'notice' => ['--stop-on-notice', 'notices'],
    'deprecation' => ['--stop-on-deprecation', 'deprecates'],
    'skipped' => ['--stop-on-skipped', 'is skipped'],
])->skipOnWindows();
