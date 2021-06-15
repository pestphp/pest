<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(1.0)->toBeFloat();
    expect(1)->not->toBeFloat();
});

test('failures', function () {
    expect(42)->toBeFloat();
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(log(3))->not->toBeFloat();
})->throws(ExpectationFailedException::class);
