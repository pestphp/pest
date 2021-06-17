<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect(1.1)->toBeScalar();
});

test('failures', function () {
    expect(null)->toBeScalar();
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(42)->not->toBeScalar();
})->throws(ExpectationFailedException::class);
