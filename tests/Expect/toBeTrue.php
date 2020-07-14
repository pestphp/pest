<?php

use PHPUnit\Framework\ExpectationFailedException;

test('strict comparisons', function () {
    expect(true)->toBeTrue();
});

test('failures', function () {
    expect('')->toBeTrue();
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(false)->not->toBe(false);
})->throws(ExpectationFailedException::class);
