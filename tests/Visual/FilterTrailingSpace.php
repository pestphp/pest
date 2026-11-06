<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

$runWithIdeFilter = function (string $description): Process {
    $filter = sprintf(<<<'REGEX'
        /^(P\\)?Tests\\Fixtures\\Suites\\TrailingSpaceTest::%s(\swith\s(data\sset\s".*"|\(.*\))(\s\/\s(data\sset\s".*"|\(.*\)))*(\s#\d+)?)?$/
        REGEX, $description);

    $process = new Process([
        'php',
        'bin/pest',
        'tests/Fixtures/Suites/TrailingSpaceTest.php',
        '--filter='.$filter,
        '--colors=never',
    ], dirname(__DIR__, 2), ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1']);

    $process->run();

    return $process;
};

test('filter matches a test name with a trailing space', function () use ($runWithIdeFilter): void {
    $process = $runWithIdeFilter('example\stest\s');

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('1 passed');
})->skipOnWindows();

test('filter without a trailing space does not match a test name with a trailing space', function () use ($runWithIdeFilter): void {
    $process = $runWithIdeFilter('example\stest');

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('1 passed');
})->skipOnWindows();

test('filter matches a dataset test name with a trailing space', function () use ($runWithIdeFilter): void {
    $process = $runWithIdeFilter('example\stest\swith\sdataset\s');

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('example test with dataset with (1)')
        ->toContain('1 passed');
})->skipOnWindows();
