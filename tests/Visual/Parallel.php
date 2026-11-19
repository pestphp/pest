<?php

use Symfony\Component\Process\Process;

$parallel = function (string ...$arguments): Process {
    $process = new Process(
        ['php', 'bin/pest', '--parallel', '--processes=3', ...$arguments],
        dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1'],
    );

    $process->setTimeout(300.0);

    return $process;
};

$run = function (string ...$arguments) use ($parallel): ?string {
    $process = $parallel(...$arguments);
    $process->run();

    return removeAnsiEscapeSequences($process->getOutput());
};

test('parallel', function () use ($parallel, $run): void {
    $process = $parallel('tests/Fixtures/Suites/SuccessfulRun');
    $process->mustRun();
    $profileOutput = $run('tests/Fixtures/Suites/SuccessOnly.php', '--profile');

    expect(removeAnsiEscapeSequences($process->getOutput()))->toContain(
        'Tests:    1 deprecated, 1 warning, 1 incomplete, 1 notice, 1 todo, 1 skipped, 2 passed (5 assertions)',
        'Parallel: 3 processes',
    )->and($profileOutput)->toContain(
        'Top 10 slowest tests:',
        'can pass with comparison',
        'can also pass',
    );
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

test('parallel lets extensions register event subscribers', function (): void {
    $process = new Process(
        ['php', 'bin/pest', '--configuration', 'tests/Fixtures/Suites/ParallelExtensionSubscriber.xml', '--parallel', '--processes=2'],
        dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1'],
    );

    $process->run();

    expect($process->getExitCode())->toBe(0)
        ->and(removeAnsiEscapeSequences($process->getOutput()))
        ->not->toContain('failed')
        ->toContain('Parallel: 2 processes');
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

test('parallel accepts the options that the workers ignore', function (string $option) use ($run): void {
    expect($run('tests/Fixtures/Inheritance', $option))
        ->toContain('Tests:    1 skipped, 1 passed (1 assertions)')
        ->toContain('Parallel: 3 processes');
})->with([
    '--no-results',
    '--do-not-cache-result',
    '--do-not-record-test-run-history',
])->skipOnWindows();

test('parallel records the test run history', function () use ($run): void {
    $cacheDirectory = sys_get_temp_dir().'/pest-parallel-history-'.bin2hex(random_bytes(8));

    $run('tests/Fixtures/Inheritance', '--cache-directory='.$cacheDirectory);

    expect($cacheDirectory.'/test-run-history')->toBeFile();

    unlink($cacheDirectory.'/test-run-history');
    rmdir($cacheDirectory);
})->skipOnWindows();

test('parallel does not record the test run history when asked not to', function (string $option) use ($run): void {
    $cacheDirectory = sys_get_temp_dir().'/pest-parallel-history-'.bin2hex(random_bytes(8));

    expect($run('tests/Fixtures/Inheritance', '--cache-directory='.$cacheDirectory, $option))
        ->toContain('Tests:    1 skipped, 1 passed (1 assertions)')
        ->and($cacheDirectory.'/test-run-history')->not->toBeFile();

    rmdir($cacheDirectory);
})->with([
    '--do-not-cache-result',
    '--do-not-record-test-run-history',
])->skipOnWindows();

test('parallel group-filtered run is not considered empty when a worker ends on a fully filtered file', function (): void {
    $process = new Process(
        ['php', 'bin/pest', '--parallel', '--processes=1', '--group=filtered', 'tests/Fixtures/Suites/ParallelGroupFilteredLastFile'],
        dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1'],
    );

    $process->run();

    expect(removeAnsiEscapeSequences($process->getOutput()))->toContain('Tests:    1 passed (1 assertions)')
        ->and($process->getExitCode())->toBe(0);
})->skipOnWindows();

test('parallel group-filtered run still fails when the selection matches no tests', function (): void {
    $process = new Process(
        ['php', 'bin/pest', '--parallel', '--processes=3', '--group=missing', 'tests/Fixtures/Suites/ParallelGroupFilteredLastFile'],
        dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1'],
    );

    $process->run();

    expect(removeAnsiEscapeSequences($process->getOutput()))->toContain('No tests found.')
        ->and($process->getExitCode())->toBe(1);
})->skipOnWindows();
