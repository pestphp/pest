<?php

use PHPUnit\Framework\ExpectationFailedException;

test('passes strings', function () {
    expect([1, 2, 42])->toContain(42);
});

test('passes arrays', function () {
    expect('Nuno')->toContain('Nu');
});

test('failures', function () {
    expect([1, 2, 42])->toContain(3);
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect([1, 2, 42])->not->toContain(42);
})->throws(ExpectationFailedException::class);
