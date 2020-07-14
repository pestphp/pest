<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(asin(2))->toBeNan();
    expect(log(0))->not->toBeNan();
});

test('failures', function () {
    expect(1)->toBeNan();
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(acos(1.5))->not->toBeNan();
})->throws(ExpectationFailedException::class);
