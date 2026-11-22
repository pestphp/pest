<?php

declare(strict_types=1);

use Pest\Plugins\Parallel\Support\SlowestFirst;
use Pest\Plugins\Shard;
use Pest\TestSuite;
use PHPUnit\Runner\TestSuiteSorter;

function slowestFirstSourceFile(string $relative): string
{
    return (string) realpath(dirname(__DIR__, 4).'/src/'.$relative);
}

it('keeps the order of the suite when there are no timings', function (): void {
    $groups = [[slowestFirstSourceFile('Plugins/Shard.php')], [slowestFirstSourceFile('TestSuite.php')]];

    expect(SlowestFirst::fromClassTimings([])->order($groups))->toBe($groups);
});

it('sends the files of the slowest test classes first', function (): void {
    $order = SlowestFirst::fromClassTimings([
        Shard::class => 1.0,
        TestSuite::class => 5.0,
        SlowestFirst::class => 3.0,
    ])->order([
        [slowestFirstSourceFile('Plugins/Shard.php')],
        [slowestFirstSourceFile('TestSuite.php')],
        [slowestFirstSourceFile('Plugins/Parallel/Support/SlowestFirst.php')],
    ]);

    expect($order)->toBe([
        [slowestFirstSourceFile('TestSuite.php')],
        [slowestFirstSourceFile('Plugins/Parallel/Support/SlowestFirst.php')],
        [slowestFirstSourceFile('Plugins/Shard.php')],
    ]);
});

it('gives the median time to a file without a timing', function (): void {
    $order = SlowestFirst::fromClassTimings([
        Shard::class => 1.0,
        TestSuite::class => 5.0,
        SlowestFirst::class => 3.0,
    ])->order([
        [slowestFirstSourceFile('Plugins/Shard.php')],
        ['/new/file.php'],
        [slowestFirstSourceFile('TestSuite.php')],
    ]);

    expect($order)->toBe([
        [slowestFirstSourceFile('TestSuite.php')],
        ['/new/file.php'],
        [slowestFirstSourceFile('Plugins/Shard.php')],
    ]);
});

it('sorts a group of files by the sum of their times', function (): void {
    $order = SlowestFirst::fromClassTimings([
        Shard::class => 2.0,
        TestSuite::class => 3.0,
        SlowestFirst::class => 4.0,
    ])->order([
        [slowestFirstSourceFile('Plugins/Parallel/Support/SlowestFirst.php')],
        [slowestFirstSourceFile('Plugins/Shard.php'), slowestFirstSourceFile('TestSuite.php')],
    ]);

    expect($order)->toBe([
        [slowestFirstSourceFile('Plugins/Shard.php'), slowestFirstSourceFile('TestSuite.php')],
        [slowestFirstSourceFile('Plugins/Parallel/Support/SlowestFirst.php')],
    ]);
});

it('finds the file of a pest test class without its P prefix', function (): void {
    $class = substr($this::class, 2);

    $order = SlowestFirst::fromClassTimings([
        $class => 9.0,
        Shard::class => 1.0,
    ])->order([
        [slowestFirstSourceFile('Plugins/Shard.php')],
        [(string) realpath(__FILE__)],
    ]);

    expect($order)->toBe([
        [(string) realpath(__FILE__)],
        [slowestFirstSourceFile('Plugins/Shard.php')],
    ]);
});

it('ignores a test class that is not loaded', function (): void {
    $groups = [[slowestFirstSourceFile('Plugins/Shard.php')], [slowestFirstSourceFile('TestSuite.php')]];

    expect(SlowestFirst::fromClassTimings(['Tests\\Missing\\GoneTest' => 10.0])->order($groups))->toBe($groups);
});

it('orders the files only when the run has no execution order of its own', function (bool $orderGiven, int $executionOrder, int $executionOrderDefects, bool $applies): void {
    expect(SlowestFirst::appliesTo($orderGiven, $executionOrder, $executionOrderDefects))->toBe($applies);
})->with([
    'default' => [false, TestSuiteSorter::ORDER_DEFAULT, TestSuiteSorter::ORDER_DEFAULT, true],
    'random' => [false, TestSuiteSorter::ORDER_RANDOMIZED, TestSuiteSorter::ORDER_DEFAULT, false],
    'reverse' => [false, TestSuiteSorter::ORDER_REVERSED, TestSuiteSorter::ORDER_DEFAULT, false],
    'duration' => [false, TestSuiteSorter::ORDER_DURATION_DESCENDING, TestSuiteSorter::ORDER_DEFAULT, false],
    'size' => [false, TestSuiteSorter::ORDER_SIZE_ASCENDING, TestSuiteSorter::ORDER_DEFAULT, false],
    'defects' => [false, TestSuiteSorter::ORDER_DEFAULT, TestSuiteSorter::ORDER_DEFECTS_FIRST, false],
    'explicit default' => [true, TestSuiteSorter::ORDER_DEFAULT, TestSuiteSorter::ORDER_DEFAULT, false],
]);
