<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect([])->toBeIterable()
        ->and(null)->not->toBeIterable();
});

test('failures', function (): void {
    expect(42)->toBeIterable();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(42)->toBeIterable('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    function gen(): iterable
    {
        yield 1;
        yield 2;
        yield 3;
    }

    expect(gen())->not->toBeIterable();
})->throws(ExpectationFailedException::class);
