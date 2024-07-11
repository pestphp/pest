<?php

use PHPUnit\Framework\ExpectationFailedException;

$object = new class
{
    public function foo(): void {}

    public function bar(): void {}
};

test('pass', function () use ($object) {
    expect($object)->toHaveMethods(['foo', 'bar']);
});

test('failures', function () use ($object) {
    expect($object)->toHaveMethods(['foo', 'bar', 'baz']);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () use ($object) {
    expect($object)->toHaveMethods(['foo', 'bar', 'baz'], 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () use ($object) {
    expect($object)->not->toHaveMethods(['foo', 'bar']);
})->throws(ExpectationFailedException::class);
