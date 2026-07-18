<?php

use PHPUnit\Framework\ExpectationFailedException;

test('passes', function (): void {
    expect(41)->toBeLessThanOrEqual(42)
        ->and(4)->toBeLessThanOrEqual(4);
});

test('passes with DateTime and DateTimeImmutable', function (): void {
    $now = new DateTime;
    $past = (new DateTimeImmutable)->modify('-1 day');

    expect($now)->toBeLessThanOrEqual($now)
        ->and($past)->toBeLessThanOrEqual($now)
        ->and($now)->not->toBeLessThanOrEqual($past);
});

test('passes with strings', function (): void {
    expect('a')->toBeLessThanOrEqual('b')
        ->toBeLessThanOrEqual('a');
});

test('failures', function (): void {
    expect(4)->toBeLessThanOrEqual(3.9);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(4)->toBeLessThanOrEqual(3.9, 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(5)->not->toBeLessThanOrEqual(5);
})->throws(ExpectationFailedException::class);
