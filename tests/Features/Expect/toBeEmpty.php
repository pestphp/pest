<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect([])->toBeEmpty()
        ->and(null)->toBeEmpty();
});

test('failures', function (): void {
    expect([1, 2])->toBeEmpty()
        ->and(' ')->toBeEmpty();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect([1, 2])->toBeEmpty('oh no!')
        ->and(' ')->toBeEmpty('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect([])->not->toBeEmpty()
        ->and(null)->not->toBeEmpty();
})->throws(ExpectationFailedException::class);
