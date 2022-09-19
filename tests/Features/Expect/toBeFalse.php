<?php

use PHPUnit\Framework\ExpectationFailedException;

test('strict comparisons', function () {
    expect(false)->toBeFalse();
});

test('failures', function () {
    expect('')->toBeFalse();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('')->toBeFalse('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect(false)->not->toBe(false);
})->throws(ExpectationFailedException::class);
