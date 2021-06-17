<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(true)->toBeBool();
    expect(0)->not->toBeBool();
});

test('failures', function () {
    expect(null)->toBeBool();
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(false)->not->toBeBool();
})->throws(ExpectationFailedException::class);
