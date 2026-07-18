<?php

use PHPUnit\Framework\ExpectationFailedException;

test('passes', function (): void {
    expect(41)->toBeLessThan(42)
        ->and(4)->toBeLessThan(5);
});

test('passes with DateTime and DateTimeImmutable', function (): void {
    $now = new DateTime;
    $past = (new DateTimeImmutable)->modify('-1 day');

    expect($past)->toBeLessThan($now)
        ->and($now)->not->toBeLessThan($now);
});

test('passes with strings', function (): void {
    expect('a')->toBeLessThan('b')->not->toBeLessThan('a');
});

test('failures', function (): void {
    expect(4)->toBeLessThan(4);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(4)->toBeLessThan(4, 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(5)->not->toBeLessThan(6);
})->throws(ExpectationFailedException::class);
