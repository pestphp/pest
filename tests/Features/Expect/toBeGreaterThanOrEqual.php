<?php

use PHPUnit\Framework\ExpectationFailedException;

test('passes', function (): void {
    expect(42)->toBeGreaterThanOrEqual(41)
        ->and(4)->toBeGreaterThanOrEqual(4);
});

test('passes with DateTime and DateTimeImmutable', function (): void {
    $now = new DateTime;
    $past = (new DateTimeImmutable)->modify('-1 day');

    expect($now)->toBeGreaterThanOrEqual($now)
        ->toBeGreaterThanOrEqual($past)
        ->and($past)->not->toBeGreaterThanOrEqual($now);
});

test('passes with strings', function (): void {
    expect('b')->toBeGreaterThanOrEqual('a')
        ->and('a')->toBeGreaterThanOrEqual('a');
});

test('failures', function (): void {
    expect(4)->toBeGreaterThanOrEqual(4.1);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(4)->toBeGreaterThanOrEqual(4.1, 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(5)->not->toBeGreaterThanOrEqual(5);
})->throws(ExpectationFailedException::class);
