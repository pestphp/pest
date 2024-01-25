<?php

use PHPUnit\Framework\ExpectationFailedException;

test('passes arrays', function () {
    expect([1, 2, 42])->toContainEqual('42');
});

test('passes arrays with multiple needles', function () {
    expect([1, 2, 42])->toContainEqual('42', '2');
});

test('failures', function () {
    expect([1, 2, 42])->toContainEqual('3');
})->throws(ExpectationFailedException::class);

test('failures with multiple needles (all failing)', function () {
    expect([1, 2, 42])->toContainEqual('3', '4');
})->throws(ExpectationFailedException::class);

test('failures with multiple needles (some failing)', function () {
    expect([1, 2, 42])->toContainEqual('1', '3', '4');
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect([1, 2, 42])->not->toContainEqual('42');
})->throws(ExpectationFailedException::class);

test('not failures with multiple needles (all failing)', function () {
    expect([1, 2, 42])->not->toContainEqual('42', '2');
})->throws(ExpectationFailedException::class);

test('not failures with multiple needles (some failing)', function () {
    expect([1, 2, 42])->not->toContainEqual('42', '1');
})->throws(ExpectationFailedException::class);
