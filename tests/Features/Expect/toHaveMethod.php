<?php

use PHPUnit\Framework\ExpectationFailedException;

$object = new class
{
    public function foo(): void
    {
    }
};

test('pass', function () use ($object) {
    expect($object)->toHaveMethod('foo')
        ->and($object)->toHaveMethod('foo')
        ->and($object)->not->toHaveMethod('fooNull');
});

test('failures', function () use ($object) {
    expect($object)->toHaveMethod('bar');
})->throws(ExpectationFailedException::class);

test('failures with message', function () use ($object) {
    expect($object)->toHaveMethod(name: 'bar', message: 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () use ($object) {
    expect($object)->not->toHaveMethod('foo');
})->throws(ExpectationFailedException::class);
