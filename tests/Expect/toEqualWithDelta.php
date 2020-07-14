<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(1.0)->toEqualWithDelta(1.3, .4);
});

test('failures', function () {
    expect(1.0)->toEqualWithDelta(1.5, .1);
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(1.0)->not->toEqualWithDelta(1.6, .7);
})->throws(ExpectationFailedException::class);
