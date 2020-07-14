<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('1.1')->toBeString();
    expect(1.1)->not->toBeString();
});

test('failures', function () {
    expect(null)->toBeString();
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect('42')->not->toBeString();
})->throws(ExpectationFailedException::class);
