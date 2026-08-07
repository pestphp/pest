<?php

use Symfony\Component\Process\Process;

$run = function (): ?string {
    $process = new Process(
        array_merge(['php', 'bin/pest', '--parallel', '--processes=3'], func_get_args()),
        dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1'],
    );

    $process->setTimeout(300.0);

    $process->run();

    return removeAnsiEscapeSequences($process->getOutput());
};

test('parallel', function () use ($run): void {
    $output = $run('--exclude-group=integration');
    $output = implode("\n", array_slice(explode("\n", (string) $output), -10));

    if (getenv('REBUILD_SNAPSHOTS')) {
        preg_match('/Tests:\s+(.+\(\d+ assertions\))/', $output, $matches);

        $file = file_get_contents(__FILE__);
        $file = preg_replace(
            '/\$expected = \'.*?\';/',
            "\$expected = '1 deprecated, 4 warnings, 5 incomplete, 3 notices, 40 todos, 26 skipped, 1544 passed (3350 assertions)';",
            $file,
        );
        file_put_contents(__FILE__, $file);
    }

    $expected = '1 deprecated, 4 warnings, 5 incomplete, 3 notices, 40 todos, 26 skipped, 1544 passed (3350 assertions)';

    expect($output)
        ->toContain("Tests:    {$expected}")
        ->toContain('Parallel: 3 processes');
})->skipOnWindows();

test('a parallel test can extend another test with same name', function () use ($run): void {
    expect($run('tests/Fixtures/Inheritance'))->toContain('Tests:    1 skipped, 1 passed (1 assertions)');
})->skipOnWindows();

test('parallel reports invalid datasets as failures', function () use ($run): void {
    expect($run('tests/Fixtures/Suites/ParallelInvalidDataset'))
        ->toContain("A dataset named [missing.dataset] does not exist. You may create one using `dataset('missing.dataset', ['a', 'b']);`.")
        ->toContain('Tests:    1 failed, 1 passed (1 assertions)')
        ->toContain('Parallel: 3 processes');
})->skipOnWindows();

test('parallel can have multiple exclude-groups', function () use ($run): void {
    $singleExclude = $run('--exclude-group=integration');
    $doubleExclude = $run('--exclude-group=integration', '--exclude-group=container');

    preg_match('/(\d+) passed/', (string) $singleExclude, $singleMatch);
    preg_match('/(\d+) passed/', (string) $doubleExclude, $doubleMatch);

    expect((int) $doubleMatch[1])->toBeLessThan((int) $singleMatch[1])
        ->and($doubleExclude)->toContain('Parallel: 3 processes');
})->skipOnWindows();

test('parallel can have multiple groups', function () use ($run): void {
    $output = $run('tests/Fixtures/Suites/MultipleGroups', '--group=one', '--group=two');

    expect($output)
        ->toContain('Tests:    2 passed (2 assertions)')
        ->toContain('Parallel: 3 processes');
})->skipOnWindows();
