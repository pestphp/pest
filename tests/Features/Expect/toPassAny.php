<?php

use PHPUnit\Framework\AssertionFailedError;

it('throws first exception', function (array $tests) {
    $mappedTests = array_map(fn ($i) => fn ($e) => $e->toContain($i), $tests);
    expect(fn () => expect('Foo')->toPassAny(...$mappedTests))
        ->toThrow(fn (AssertionFailedError $e) => expect($e->getMessage())->toContain('Foo')->toContain($tests[0]));

    // Make sure inverted test has the opposite effect
    expect(fn () => expect('Foo')->not->toPassAny(...$mappedTests))->not->toThrow(\Throwable::class);
})->with([
    [['First']],
    [['First', 'Second']],
    [['First', 'Second', 'Third']],
]);

it('succeeds with valid tests', function ($tests) {
    $mappedTests = array_map(fn ($i) => fn ($e) => $e->toBe($i), $tests);
    expect(fn () => expect('Foo')->toPassAny(...$mappedTests))
        ->not->toThrow(\Throwable::class);

    // Make sure inverted test has the opposite effect
    expect(fn () => expect('Foo')->not->toPassAny(...$mappedTests))
        ->toThrow(AssertionFailedError::class);
})->with([
    [['Fail', 'Fail', 'Fail', 'Foo']],
    [['Fail', 'Fail', 'Foo', 'Fail']],
    [['Fail', 'Foo', 'Fail', 'Fail']],
    [['Foo', 'Fail', 'Fail', 'Fail']],
    [['Foo', 'Foo', 'Foo', 'Foo']],
    [['Fail', 'Foo']],
    [['Foo']],
    [[]],
]);
