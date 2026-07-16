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

test('parallel group-filtered run is not considered empty when workers end on fully filtered files', function () {
    // Regression: a worker's TestResult only carries `numberOfTests` of its
    // LAST executed file (PHPUnit's Collector overwrites it per execution).
    // With `--processes=1` the single worker deterministically ends on
    // ZTest.php, whose only test is excluded by the group filter — before the
    // fix the merged result claimed "no tests" and exited 1 despite the green
    // summary (PHPUnit enables failOnEmptyTestSuite implicitly for --group).
    $process = new Process(
        ['php', 'bin/pest', '--parallel', '--processes=1', '--group=filtered-group', 'tests/.tests/ParallelGroupFilteredLastFile'],
        dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true'],
    );

    $process->run();

    expect(removeAnsiEscapeSequences($process->getOutput()))
        ->toContain('Tests:    1 passed (1 assertions)');

    expect($process->getExitCode())->toBe(0);
})->skipOnWindows();

test('parallel group-filtered run still fails when the selection matches no tests at all', function () {
    // Companion guard: a genuinely empty selection must keep exiting non-zero
    // (failOnEmptyTestSuite) — the fix must not mask real "0 tests" runs.
    $process = new Process(
        ['php', 'bin/pest', '--parallel', '--processes=3', '--group=group-that-does-not-exist', 'tests/.tests/ParallelGroupFilteredLastFile'],
        dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true'],
    );

    $process->run();

    expect($process->getExitCode())->not->toBe(0);
})->skipOnWindows();
