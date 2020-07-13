<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect([1, 2, 3])->toContainOnly('int');
    expect(['hello', 'world'])->toContainOnly('string');
});

test('failures', function () {
    expect([1, 2, '3'])->toContainOnly('string');
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect([1, 2, 3])->not->toContainOnly('int');
    expect(['hello', 'world'])->not->toContainOnly('string');
})->throws(ExpectationFailedException::class);
