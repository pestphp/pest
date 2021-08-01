<?php

use PHPUnit\Framework\ExpectationFailedException;

test('passes strings', function () {
    expect('Nuno')->toContain('Nu');
});

test('passes strings with multiple needles', function () {
    expect('Nuno')->toContain('Nu', 'no');
});

test('passes arrays', function () {
    expect([1, 2, 42])->toContain(42);
});

test('passes arrays with multiple needles', function () {
    expect([1, 2, 42])->toContain(42, 2);
});

test('passes with array needles', function () {
    expect([[1, 2, 3], 2, 42])->toContain(42, [1, 2, 3]);
});

test('failures', function () {
    expect([1, 2, 42])->toContain(3);
})->throws(ExpectationFailedException::class);

test('failures with multiple needles (all failing)', function () {
    expect([1, 2, 42])->toContain(3, 4);
})->throws(ExpectationFailedException::class);

test('failures with multiple needles (some failing)', function () {
    expect([1, 2, 42])->toContain(1, 3, 4);
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect([1, 2, 42])->not->toContain(42);
})->throws(ExpectationFailedException::class);

test('not failures with multiple needles (all failing)', function () {
    expect([1, 2, 42])->not->toContain(42, 2);
})->throws(ExpectationFailedException::class);

test('not failures with multiple needles (some failing)', function () {
    expect([1, 2, 42])->not->toContain(42, 1);
})->throws(ExpectationFailedException::class);
