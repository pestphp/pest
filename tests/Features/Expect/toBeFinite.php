<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect(1.5)->toBeFinite()
        ->and(-10.2)->toBeFinite()
        ->and(0)->toBeFinite()
        ->and(42)->toBeFinite()
        ->and(INF)->not->toBeFinite()
        ->and(-INF)->not->toBeFinite()
        ->and(NAN)->not->toBeFinite();
});

test('failures', function (): void {
    expect(INF)->toBeFinite();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(INF)->toBeFinite('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(42)->not->toBeFinite();
})->throws(ExpectationFailedException::class);
