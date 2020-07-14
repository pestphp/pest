<?php

use PHPUnit\Framework\ExpectationFailedException;

test('passes', function () {
    expect(42)->toBeGreaterThanOrEqual(41);
    expect(4)->toBeGreaterThanOrEqual(4);
});

test('failures', function () {
    expect(4)->toBeGreaterThanOrEqual(4.1);
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(5)->not->toBeGreaterThanOrEqual(5);
})->throws(ExpectationFailedException::class);
