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

it('parses non-Tests namespaced classes', function () {
    $output = <<<'OUT'
 - P\Acme\Sharding\OneTest::test_foo
 - P\Acme\Sharding\TwoTest::test_bar
 - App\Suite\BazTest::test_qux
OUT;

    expect(Shard::parseListTestsOutput($output))->toBe([
        'Acme\\Sharding\\OneTest',
        'Acme\\Sharding\\TwoTest',
        'App\\Suite\\BazTest',
    ]);
});

it('parses unnamespaced top-level classes', function () {
    $output = ' - P\FooTest::test_bar';

    expect(Shard::parseListTestsOutput($output))->toBe(['FooTest']);
});

it('strips the P\\ Pest prefix but keeps the rest of the FQCN', function () {
    $output = <<<'OUT'
 - P\Acme\OneTest::a
 - Acme\TwoTest::b
OUT;

    expect(Shard::parseListTestsOutput($output))->toBe([
        'Acme\\OneTest',
        'Acme\\TwoTest',
    ]);
});

it('ignores junk lines that lack the " - …::" framing', function () {
    $output = <<<'OUT'
 INFO  Available tests:

There were errors:
garbage ::: not a test
 - P\Acme\RealTest::method
OUT;

    expect(Shard::parseListTestsOutput($output))->toBe(['Acme\\RealTest']);
});

it('builds the list-tests command with the forwarded --test-directory', function () {
    $command = Shard::buildListTestsCommand(['bin/pest', '--update-shards'], 'custom/suite');

    expect($command)->toBe([
        'php',
        'bin/pest',
        '--update-shards',
        '--test-directory=custom/suite',
        '--list-tests',
    ]);
});

it('strips --parallel and -p when building the list-tests command', function () {
    $command = Shard::buildListTestsCommand(
        ['bin/pest', '--parallel', '--update-shards', '-p'],
        'tests',
    );

    expect($command)->toBe([
        'php',
        'bin/pest',
        '--update-shards',
        '--test-directory=tests',
        '--list-tests',
    ]);
});

it('forwards --test-directory even when input arguments include one', function () {
    $command = Shard::buildListTestsCommand(['bin/pest'], 'suites');

    expect($command)->toContain('--test-directory=suites');
});
