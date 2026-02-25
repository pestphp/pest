<?php

use Pest\Plugins\ShardByTime;
use Symfony\Component\Console\Output\NullOutput;

function callShardByTimeMethod(string $method, array $args): mixed
{
    $plugin = new ShardByTime(new NullOutput);
    $reflection = new ReflectionMethod($plugin, $method);

    return $reflection->invoke($plugin, ...$args);
}

it('skips when --shard is not present', function () {
    $plugin = new ShardByTime(new NullOutput);

    $arguments = $plugin->handleArguments(['./vendor/bin/pest', '--filter', 'SomeTest']);

    expect($arguments)->toBe(['./vendor/bin/pest', '--filter', 'SomeTest']);
});

it('skips when --shard-timing is not set', function () {
    unset($_SERVER['PEST_SHARD_TIMING']);

    $plugin = new ShardByTime(new NullOutput);

    $arguments = $plugin->handleArguments(['./vendor/bin/pest', '--shard=1/2']);

    expect($arguments)->toBe(['./vendor/bin/pest', '--shard=1/2']);
});

it('parses JUnit XML testsuite class times', function () {
    $junitXml = tempnam(sys_get_temp_dir(), 'pest-junit-');
    file_put_contents($junitXml, <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="Tests\Unit\FooTest" class="Tests\Unit\FooTest" tests="2" time="1.5">
    <testcase name="it works" class="Tests\Unit\FooTest" time="1.0"/>
    <testcase name="it also works" class="Tests\Unit\FooTest" time="0.5"/>
  </testsuite>
  <testsuite name="Tests\Unit\BarTest" class="Tests\Unit\BarTest" tests="1" time="3.0">
    <testcase name="it runs" class="Tests\Unit\BarTest" time="3.0"/>
  </testsuite>
</testsuites>
XML);

    $result = callShardByTimeMethod('loadClassTimesFromJunitXml', [$junitXml]);

    expect($result)->toBe([
        'Tests\Unit\FooTest' => 1.5,
        'Tests\Unit\BarTest' => 3.0,
    ]);

    unlink($junitXml);
});

it('falls back to testcase elements when no testsuite has class attribute', function () {
    $junitXml = tempnam(sys_get_temp_dir(), 'pest-junit-');
    file_put_contents($junitXml, <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="">
    <testcase name="it works" class="Tests\Unit\FooTest" time="1.0"/>
    <testcase name="it also works" class="Tests\Unit\FooTest" time="0.5"/>
    <testcase name="it runs" class="Tests\Unit\BarTest" time="2.0"/>
  </testsuite>
</testsuites>
XML);

    $result = callShardByTimeMethod('loadClassTimesFromJunitXml', [$junitXml]);

    expect($result)->toBe([
        'Tests\Unit\FooTest' => 1.5,
        'Tests\Unit\BarTest' => 2.0,
    ]);

    unlink($junitXml);
});

it('returns null for non-existent JUnit XML file', function () {
    $result = callShardByTimeMethod('loadClassTimesFromJunitXml', ['/tmp/non-existent-junit.xml']);

    expect($result)->toBeNull();
});

it('returns null for empty JUnit XML', function () {
    $junitXml = tempnam(sys_get_temp_dir(), 'pest-junit-');
    file_put_contents($junitXml, <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
</testsuites>
XML);

    $result = callShardByTimeMethod('loadClassTimesFromJunitXml', [$junitXml]);

    expect($result)->toBeNull();

    unlink($junitXml);
});

it('distributes tests using LPT bin-packing', function () {
    $tests = [
        'Tests\Unit\FastTest',
        'Tests\Unit\MediumTest',
        'Tests\Unit\SlowTest',
    ];
    $classTimes = [
        'Tests\Unit\SlowTest' => 10.0,
        'Tests\Unit\MediumTest' => 5.0,
        'Tests\Unit\FastTest' => 1.0,
    ];

    $shard1 = callShardByTimeMethod('shardByTime', [$tests, 2, 1, $classTimes]);
    $shard2 = callShardByTimeMethod('shardByTime', [$tests, 2, 2, $classTimes]);

    // LPT: SlowTest(10) -> shard1, MediumTest(5) -> shard2, FastTest(1) -> shard2
    expect($shard1)->toBe(['Tests\Unit\SlowTest'])
        ->and($shard2)->toBe(['Tests\Unit\MediumTest', 'Tests\Unit\FastTest']);
});

it('uses default time of 1s for tests without timing data', function () {
    $tests = [
        'Tests\Unit\KnownTest',
        'Tests\Unit\UnknownTest',
    ];
    $classTimes = [
        'Tests\Unit\KnownTest' => 5.0,
    ];

    $shard1 = callShardByTimeMethod('shardByTime', [$tests, 2, 1, $classTimes]);
    $shard2 = callShardByTimeMethod('shardByTime', [$tests, 2, 2, $classTimes]);

    // KnownTest(5.0) -> shard1, UnknownTest(1.0 default) -> shard2
    expect($shard1)->toBe(['Tests\Unit\KnownTest'])
        ->and($shard2)->toBe(['Tests\Unit\UnknownTest']);
});

it('balances shards evenly across multiple bins', function () {
    $tests = [
        'Tests\Unit\A',
        'Tests\Unit\B',
        'Tests\Unit\C',
        'Tests\Unit\D',
    ];
    $classTimes = [
        'Tests\Unit\A' => 4.0,
        'Tests\Unit\B' => 3.0,
        'Tests\Unit\C' => 2.0,
        'Tests\Unit\D' => 1.0,
    ];

    $shard1 = callShardByTimeMethod('shardByTime', [$tests, 3, 1, $classTimes]);
    $shard2 = callShardByTimeMethod('shardByTime', [$tests, 3, 2, $classTimes]);
    $shard3 = callShardByTimeMethod('shardByTime', [$tests, 3, 3, $classTimes]);

    // LPT: A(4)->s1, B(3)->s2, C(2)->s3, D(1)->s3
    expect($shard1)->toBe(['Tests\Unit\A'])
        ->and($shard2)->toBe(['Tests\Unit\B'])
        ->and($shard3)->toBe(['Tests\Unit\C', 'Tests\Unit\D']);
});

it('returns empty array for out-of-range shard index', function () {
    $tests = ['Tests\Unit\A'];
    $classTimes = ['Tests\Unit\A' => 1.0];

    $result = callShardByTimeMethod('shardByTime', [$tests, 2, 3, $classTimes]);

    expect($result)->toBe([]);
});

it('parses result cache with P prefix', function () {
    $cacheFile = tempnam(sys_get_temp_dir(), 'pest-cache-');
    file_put_contents($cacheFile, json_encode([
        'version' => 1,
        'defects' => [],
        'times' => [
            'P\Tests\Unit\FooTest::test_one' => 0.5,
            'P\Tests\Unit\FooTest::test_two' => 0.3,
            'P\Tests\Unit\BarTest::test_bar' => 1.2,
        ],
    ]));

    $result = callShardByTimeMethod('loadClassTimesFromResultCache', [['--cache-directory', dirname($cacheFile)]]);

    // The method looks for 'test-results' or '.phpunit.result.cache' in the cache directory,
    // not an arbitrary file. We need to place the file correctly.
    unlink($cacheFile);

    // Test with properly named file
    $cacheDir = sys_get_temp_dir().'/pest-cache-test-'.uniqid();
    mkdir($cacheDir);
    file_put_contents($cacheDir.'/test-results', json_encode([
        'version' => 1,
        'defects' => [],
        'times' => [
            'P\Tests\Unit\FooTest::test_one' => 0.5,
            'P\Tests\Unit\FooTest::test_two' => 0.3,
            'P\Tests\Unit\BarTest::test_bar' => 1.2,
        ],
    ]));

    $result = callShardByTimeMethod('loadClassTimesFromResultCache', [['--cache-directory', $cacheDir]]);

    expect($result)->toBe([
        'Tests\Unit\FooTest' => 0.8,
        'Tests\Unit\BarTest' => 1.2,
    ]);

    unlink($cacheDir.'/test-results');
    rmdir($cacheDir);
});

it('returns null for missing result cache', function () {
    $result = callShardByTimeMethod('loadClassTimesFromResultCache', [['--cache-directory', '/tmp/non-existent-cache-dir']]);

    expect($result)->toBeNull();
});

it('does not produce output when shard was not used', function () {
    $output = new \Symfony\Component\Console\Output\BufferedOutput;
    $plugin = new ShardByTime($output);

    $exitCode = $plugin->addOutput(0);

    expect($exitCode)->toBe(0)
        ->and($output->fetch())->toBe('');
});
