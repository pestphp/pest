<?php

use PHPUnit\Framework\ExpectationFailedException;

expect(true)->toBeTrue()->and(false)->toBeFalse();

test('risky with no assertions', function () {
    expect(1)->toBeOneOf();
})->throwsNoExceptions();

test('to be one of', function () {
    expect(1)->toBeOneOf(fn ($e) => $e->toBe(1), fn ($e) => $e->toBe(2), fn ($e) => $e->toBe(3));
});

test('it does not short-circuit', function () {
    $executed = 0;
    expect(1)->toBeOneOf(function ($e) use (&$executed) {
        $executed++;

        return $e->toBe(1);
    }, function ($e) use (&$executed) {
        $executed++;

        return $e->toBe(2);
    }, function ($e) use (&$executed) {
        $executed++;

        return $e->toBe(3);
    });

    expect($executed)->toBe(3);
});

test('failure with multiple matches', function () {
    expect(1)->toBeOneOf(fn ($e) => $e->toBe(1), fn ($e) => $e->toBe(1));
})->throws(ExpectationFailedException::class, 'Failed asserting value matches exactly one expectation (matches: 0, 1).');

test('failure with no matches', function () {
    expect(1)->toBeOneOf(fn ($e) => $e->toBe(2), fn ($e) => $e->toBe(2));
})->throws(ExpectationFailedException::class, 'Failed asserting value matches any expectations.');
