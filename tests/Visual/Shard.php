<?php

use Symfony\Component\Process\Process;

test('shard works with processes option - fixes bug #1454', function () {
    $process = new Process([
        'php',
        'bin/pest',
        '--processes=4',
        '--shard=1/2',
        '--parallel',
        'tests/Fixtures/ExampleTest.php',
    ], dirname(__DIR__, 2), [
        'COLLISION_PRINTER' => 'DefaultPrinter',
        'COLLISION_IGNORE_DURATION' => 'true',
    ]);

    $process->run();

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())
        ->not->toContain('Unknown option "--processes"')
        ->toContain('Tests:')
        ->toContain('Shard:');
})->skipOnWindows();

test('shard removes processes from list-tests subprocess call', function () {
    // This test verifies that --processes doesn't get passed to the --list-tests call
    $process = new Process([
        'php',
        'bin/pest',
        '--processes=2',
        '--shard=1/1',
        '--parallel',
        'tests/Fixtures/ExampleTest.php',
    ], dirname(__DIR__, 2), [
        'COLLISION_PRINTER' => 'DefaultPrinter',
        'COLLISION_IGNORE_DURATION' => 'true',
    ]);

    $process->run();

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())
        ->not->toContain('Unknown option "--processes"')
        ->not->toContain('The "--processes" option requires a value')
        ->toContain('Shard:'); // Just check that shard output exists, don't be too specific
})->skipOnWindows();
