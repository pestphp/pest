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

    $expected = '2 deprecated, 4 warnings, 5 incomplete, 3 notices, 40 todos, 28 skipped, 1605 passed (3529 assertions)';

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

test('parallel functional gives each test to a worker on its own', function () use ($run): void {
    $output = $run('tests/Fixtures/Suites/ParallelFunctional/Spread', '--functional', '--verbose');

    expect(preg_match_all('/executing: .+(PestTest|PhpUnitTest)\.php.+(runs the \w+ test|runs_the_first_test|runs_a_data_set)/', (string) $output))->toBe(7)
        ->and($output)
        ->toContain('Tests:    7 passed (7 assertions)')
        ->toContain('Parallel: 3 processes');
})->skipOnWindows();

test('parallel functional runs the tests of a file whose name gives no class name', function () use ($run): void {
    expect($run('tests/PHPUnit/CustomAffixes/@#$%^&()-_=+.php', '--functional'))
        ->toContain('Tests:    1 passed (1 assertions)')
        ->toContain('Parallel: 3 processes');
})->skipOnWindows();

test('parallel functional runs the tests of its shard', function () use ($run): void {
    expect($run('tests/Fixtures/Suites/ParallelFunctional', '--functional', '--shard=1/2'))
        ->toContain('Tests:    6 passed (6 assertions)')
        ->toContain('Shard:    1 of 2 — 2 files ran, out of 4.');
})->skipOnWindows();

test('parallel functional runs the selected cases of tests that share a description across files', function (array $arguments, string $expected) use ($run): void {
    expect($run('tests/Fixtures/Suites/ParallelFunctional/SameDescription', '--functional', ...$arguments))
        ->toContain("Tests:    {$expected}")
        ->toContain('Parallel: 3 processes');
})->with([
    'without a filter' => [[], '4 passed (6 assertions)'],
    'filtered by the description' => [['--filter=shares a description'], '4 passed (6 assertions)'],
    'filtered by the file' => [['--filter=SecondTest'], '2 passed (4 assertions)'],
    'filtered by the data set' => [['--filter=shared'], '2 passed (3 assertions)'],
])->skipOnWindows();
