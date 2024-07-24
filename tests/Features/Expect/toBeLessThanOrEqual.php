<?php

use PHPUnit\Framework\ExpectationFailedException;

test('passes', function () {
    expect(41)->toBeLessThanOrEqual(42);
    expect(4)->toBeLessThanOrEqual(4);
});

test('passes with DateTime and DateTimeImmutable', function () {
    $now = new DateTime;
    $past = (new DateTimeImmutable)->modify('-1 day');

    expect($now)->toBeLessThanOrEqual($now);

    expect($past)->toBeLessThanOrEqual($now);

    expect($now)->not->toBeLessThanOrEqual($past);
});

test('passes with strings', function () {
    expect('a')->toBeLessThanOrEqual('b');
    expect('a')->toBeLessThanOrEqual('a');
});

test('failures', function () {
    expect(4)->toBeLessThanOrEqual(3.9);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect(4)->toBeLessThanOrEqual(3.9, 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect(5)->not->toBeLessThanOrEqual(5);
})->throws(ExpectationFailedException::class);
