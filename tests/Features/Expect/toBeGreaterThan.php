<?php

use PHPUnit\Framework\ExpectationFailedException;

test('passes', function (): void {
    expect(42)->toBeGreaterThan(41)
        ->and(4)->toBeGreaterThan(3.9);
});

test('passes with DateTime and DateTimeImmutable', function (): void {
    $now = new DateTime;
    $past = (new DateTimeImmutable)->modify('-1 day');

    expect($now)->toBeGreaterThan($past)
        ->and($past)->not->toBeGreaterThan($now);
});

test('passes with strings', function (): void {
    expect('b')->toBeGreaterThan('a')
        ->and('a')->not->toBeGreaterThan('a');
});

test('failures', function (): void {
    expect(4)->toBeGreaterThan(4);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(4)->toBeGreaterThan(4, 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(5)->not->toBeGreaterThan(4);
})->throws(ExpectationFailedException::class);
