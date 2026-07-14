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
            "\$expected = '2 deprecated, 4 warnings, 5 incomplete, 3 notices, 40 todos, 27 skipped, 1432 passed (3146 assertions)';",
            $file,
        );
        file_put_contents(__FILE__, $file);
    }

    $expected = '2 deprecated, 4 warnings, 5 incomplete, 3 notices, 40 todos, 27 skipped, 1432 passed (3146 assertions)';

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

test('parallel can have multiple exclude-groups', function () use ($run) {
    $singleExclude = $run('--exclude-group=integration');
    $doubleExclude = $run('--exclude-group=integration', '--exclude-group=container');

    preg_match('/(\d+) passed/', $singleExclude, $singleMatch);
    preg_match('/(\d+) passed/', $doubleExclude, $doubleMatch);

    expect((int) $doubleMatch[1])->toBeLessThan((int) $singleMatch[1]);
    expect($doubleExclude)->toContain('Parallel: 3 processes');
})->skipOnWindows();
