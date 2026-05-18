<?php

$extractTests = function (string $listTestsOutput): array {
    preg_match_all('/ - (?:P\\\\)?([^:]+)::/', $listTestsOutput, $matches);

    return array_values(array_unique($matches[1]));
};

$buildFilter = function (array $testsToRun): string {
    return addslashes(implode('|', $testsToRun));
};

test('allTests regex captures standard Tests-namespaced identifiers', function () use ($extractTests) {
    $output = <<<'OUTPUT'
Available test(s):
 - P\Tests\Unit\ExampleTest::it_works
 - P\Tests\Feature\AuthTest::it_logs_in
 - P\Tests\Unit\ExampleTest::it_does_something_else
OUTPUT;

    $tests = $extractTests($output);

    expect($tests)->toBe([
        'Tests\Unit\ExampleTest',
        'Tests\Feature\AuthTest',
    ]);
});

test('allTests regex captures non-standard directory identifiers', function () use ($extractTests) {
    $output = <<<'OUTPUT'
Available test(s):
 - P\Appmodules\Billing\tests\InvoiceTest::it_creates_invoice
 - P\Appmodules\Billing\tests\InvoiceTest::it_sends_invoice
 - P\Modules\Auth\tests\LoginTest::it_authenticates
OUTPUT;

    $tests = $extractTests($output);

    expect($tests)->toBe([
        'Appmodules\Billing\tests\InvoiceTest',
        'Modules\Auth\tests\LoginTest',
    ]);
});

test('allTests regex captures identifiers without P prefix', function () use ($extractTests) {
    $output = <<<'OUTPUT'
Available test(s):
 - Tests\Feature\BarTest::test_bar
 - App\Tests\BazTest::test_baz
OUTPUT;

    $tests = $extractTests($output);

    expect($tests)->toBe([
        'Tests\Feature\BarTest',
        'App\Tests\BazTest',
    ]);
});

test('allTests regex handles mixed standard and non-standard identifiers', function () use ($extractTests) {
    $output = <<<'OUTPUT'
Available test(s):
 - P\Tests\Unit\ExampleTest::it_works
 - P\Appmodules\Foo\tests\FooTest::it_works
 - Tests\Feature\BarTest::test_bar
 - P\Modules\Core\tests\CoreTest::it_boots
OUTPUT;

    $tests = $extractTests($output);

    expect($tests)->toBe([
        'Tests\Unit\ExampleTest',
        'Appmodules\Foo\tests\FooTest',
        'Tests\Feature\BarTest',
        'Modules\Core\tests\CoreTest',
    ]);
});

test('allTests regex does not match non-test lines', function () use ($extractTests) {
    $output = <<<'OUTPUT'
Available test(s):

 - P\Tests\Unit\ExampleTest::it_works
Some random output line
OUTPUT;

    $tests = $extractTests($output);

    expect($tests)->toBe([
        'Tests\Unit\ExampleTest',
    ]);
});

test('buildFilterArgument correctly escapes non-standard identifiers', function () use ($buildFilter) {
    $tests = [
        'Appmodules\Billing\tests\InvoiceTest',
        'Modules\Auth\tests\LoginTest',
    ];

    $filter = $buildFilter($tests);

    expect($filter)->toBe('Appmodules\\\\Billing\\\\tests\\\\InvoiceTest|Modules\\\\Auth\\\\tests\\\\LoginTest');
});

test('sharding distributes non-standard identifiers across shards', function () use ($extractTests) {
    $output = <<<'OUTPUT'
Available test(s):
 - P\Tests\Unit\ATest::it_works
 - P\Appmodules\Foo\tests\BTest::it_works
 - P\Modules\Bar\tests\CTest::it_works
 - P\Tests\Feature\DTest::it_works
OUTPUT;

    $tests = $extractTests($output);
    $total = 2;
    $chunks = array_chunk($tests, max(1, (int) ceil(count($tests) / $total)));

    expect($chunks)->toHaveCount(2)
        ->and($chunks[0])->toBe(['Tests\Unit\ATest', 'Appmodules\Foo\tests\BTest'])
        ->and($chunks[1])->toBe(['Modules\Bar\tests\CTest', 'Tests\Feature\DTest']);
});
