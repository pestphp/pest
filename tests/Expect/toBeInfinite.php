<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(log(0))->toBeInfinite();
    expect(log(1))->not->toBeInfinite();
});

test('failures', function () {
    expect(asin(2))->toBeInfinite();
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(INF)->not->toBeInfinite();
})->throws(ExpectationFailedException::class);
