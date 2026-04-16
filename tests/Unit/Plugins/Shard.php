<?php

use Pest\Plugins\Shard;

it('parses Tests\\ namespaced classes from --list-tests output', function () {
    $output = <<<'OUT'
 INFO  Available tests:

 - P\Tests\Features\After::__pest_evaluable_it_runs
 - P\Tests\Features\After::__pest_evaluable_it_runs_twice
 - P\Tests\Unit\Foo::test_bar
OUT;

    expect(Shard::parseListTestsOutput($output))->toBe([
        'Tests\\Features\\After',
        'Tests\\Unit\\Foo',
    ]);
});

it('deduplicates repeated class names from multiple test methods', function () {
    $output = <<<'OUT'
 - P\Tests\Same::method_a
 - P\Tests\Same::method_b
 - P\Tests\Same::method_c
OUT;

    expect(Shard::parseListTestsOutput($output))->toBe(['Tests\\Same']);
});

it('returns an empty list for output with no matching lines', function () {
    expect(Shard::parseListTestsOutput(''))->toBe([])
        ->and(Shard::parseListTestsOutput('some random text'))->toBe([]);
});
