<?php

use PHPUnit\Framework\ExpectationFailedException;

test('passes strings', function () {
    expect('Nuno')->toContainExact(1, 'Nu');
});

test('passes arrays', function () {
    expect([1, 42, 2, 42])->toContainExact(2, 42);
});

test('passes with array needles', function () {
    expect([[1, 2, 3], 2, 42, [1, 2, 3]])->toContainExact(2, [1, 2, 3]);
});

test('not success', function () {
    expect([1, 2, 42, 2, 97, 31, 2])->not->toContainExact(3, 97);
});

test('failures when not exact', function () {
    expect([1, 2, 42, 2, 2])->toContainExact(1, 2);
})->throws(ExpectationFailedException::class);

test('failures', function () {
    expect([1, 2, 42, 2])->toContainExact(3, 2);
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect([1, 2, 42, 2, 97, 31, 2])->not->toContainExact(3, 2);
})->throws(ExpectationFailedException::class);

test('failures when count is zero', function () {
    expect([1, 2, 42, 2, 2])->toContainExact(0, 3);
})->throws(ExpectationFailedException::class);

test('failures when count is negative', function () {
    expect([1, 2, 42, 2, 2])->toContainExact(-1, 2);
})->throws(ExpectationFailedException::class);
