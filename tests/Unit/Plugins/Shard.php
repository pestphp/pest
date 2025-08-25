<?php

use Pest\Plugins\Shard;
use Symfony\Component\Console\Output\BufferedOutput;

it('processes arguments with sharding and removes parallel options from subprocess', function () {
    $output = new BufferedOutput;
    $shard = new Shard($output);

    // Use reflection to test the private removeParallelArguments method
    $reflection = new ReflectionClass($shard);
    $method = $reflection->getMethod('removeParallelArguments');
    $method->setAccessible(true);

    $arguments = [
        'php',
        'bin/pest',
        '--processes=12',
        '--parallel',
        '--shard=1/4',
        '--verbose',
    ];

    $result = $method->invoke($shard, $arguments);

    expect($result)->toBe(['php', 'bin/pest', '--shard=1/4', '--verbose'])
        ->and($result)->not->toContain('--processes=12')
        ->and($result)->not->toContain('--parallel');
});

it('removes space-separated processes arguments', function () {
    $output = new BufferedOutput;
    $shard = new Shard($output);

    $reflection = new ReflectionClass($shard);
    $method = $reflection->getMethod('removeParallelArguments');
    $method->setAccessible(true);

    $arguments = [
        'php',
        'bin/pest',
        '--processes',
        '8',
        '--parallel',
        '--other-flag',
    ];

    $result = $method->invoke($shard, $arguments);

    expect($result)->toBe(['php', 'bin/pest', '--other-flag'])
        ->and($result)->not->toContain('--processes')
        ->and($result)->not->toContain('8')
        ->and($result)->not->toContain('--parallel');
});

it('preserves non-parallel arguments when filtering', function () {
    $output = new BufferedOutput;
    $shard = new Shard($output);

    $reflection = new ReflectionClass($shard);
    $method = $reflection->getMethod('removeParallelArguments');
    $method->setAccessible(true);

    $arguments = [
        'php',
        'bin/pest',
        '--filter',
        'UserTest',
        '--verbose',
        '--stop-on-failure',
    ];

    $result = $method->invoke($shard, $arguments);

    expect($result)->toBe($arguments); // Should be unchanged
});
