<?php

use PHPUnit\Framework\ExpectationFailedException;

test('passes', function () {
    expect(41)->toBeLessThan(42);
    expect(4)->toBeLessThan(5);
});

test('failures', function () {
    expect(4)->toBeLessThan(4);
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(5)->not->toBeLessThan(6);
})->throws(ExpectationFailedException::class);
