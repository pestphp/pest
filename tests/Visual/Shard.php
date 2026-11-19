<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

test('an empty shard runs no tests', function (int $exitCode, string ...$arguments): void {
    $process = new Process([
        PHP_BINARY, 'bin/pest', 'tests/Fixtures/DirectoryWithTests/ExampleTest.php',
        '--colors=never', '--shard=2/2', ...$arguments,
    ], dirname(__DIR__, 2));

    $process->run();

    expect($process->getExitCode())->toBe($exitCode)
        ->and(removeAnsiEscapeSequences($process->getOutput()))
        ->toContain('No tests found.', '2 of 2 — 0 files ran, out of 1.')
        ->not->toContain('example 1');
})->with([
    'sequential' => [0],
    'parallel' => [0, '--parallel', '--processes=2'],
    'sequential with fail-on-empty-test-suite' => [1, '--fail-on-empty-test-suite'],
    'parallel with fail-on-empty-test-suite' => [1, '--parallel', '--processes=2', '--fail-on-empty-test-suite'],
]);

test('an empty shard lists no tests', function (): void {
    $process = new Process([
        PHP_BINARY, 'bin/pest', 'tests/Fixtures/DirectoryWithTests/ExampleTest.php',
        '--colors=never', '--shard=2/2', '--list-tests',
    ], dirname(__DIR__, 2));

    $process->run();

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('Available test')
        ->not->toContain('__pest_evaluable_it_example_1', 'example 1');
});

test('a non-empty shard runs its assigned test', function (): void {
    $process = new Process([
        PHP_BINARY, 'bin/pest', 'tests/Fixtures/DirectoryWithTests/ExampleTest.php',
        '--colors=never', '--shard=1/2',
    ], dirname(__DIR__, 2));

    $process->run();

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('1 passed', '1 of 2 — 1 file ran, out of 1.');
});
