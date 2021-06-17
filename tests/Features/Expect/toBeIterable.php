<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect([])->toBeIterable();
    expect(null)->not->toBeIterable();
});

test('failures', function () {
    expect(42)->toBeIterable();
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    function gen(): iterable
    {
        yield 1;
        yield 2;
        yield 3;
    }

    expect(gen())->not->toBeIterable();
})->throws(ExpectationFailedException::class);
