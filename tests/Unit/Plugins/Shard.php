<?php

use Pest\Plugins\Shard;

it('distributes tests round-robin across shards', function () {
    $tests = array_map(fn (int $i): string => "Tests\\Feature\\Class{$i}", range(1, 7));

    expect(Shard::distribute($tests, 1, 3))->toBe(['Tests\\Feature\\Class1', 'Tests\\Feature\\Class4', 'Tests\\Feature\\Class7'])
        ->and(Shard::distribute($tests, 2, 3))->toBe(['Tests\\Feature\\Class2', 'Tests\\Feature\\Class5'])
        ->and(Shard::distribute($tests, 3, 3))->toBe(['Tests\\Feature\\Class3', 'Tests\\Feature\\Class6']);
});

it('assigns every test to exactly one shard', function () {
    $tests = array_map(fn (int $i): string => "Tests\\Feature\\Class{$i}", range(1, 50));

    $assigned = array_merge(...array_map(
        fn (int $shard): array => Shard::distribute($tests, $shard, 4),
        [1, 2, 3, 4],
    ));

    expect($assigned)->toHaveCount(50)
        ->and(array_unique($assigned))->toHaveCount(50);
});

it('scatters same-prefix clusters across shards', function () {
    // --list-tests returns classes sorted, so same-prefix families arrive contiguously.
    $tests = [
        ...array_map(fn (int $i): string => "Tests\\Feature\\Admin\\Class{$i}", range(1, 4)),
        ...array_map(fn (int $i): string => "Tests\\Unit\\Service\\Class{$i}", range(1, 4)),
    ];

    // Round-robin mixes both families into a shard instead of one homogeneous cluster.
    expect(Shard::distribute($tests, 1, 2))
        ->toContain('Tests\\Feature\\Admin\\Class1')
        ->toContain('Tests\\Unit\\Service\\Class1');
});
