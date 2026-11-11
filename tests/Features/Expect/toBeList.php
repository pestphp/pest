<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect([1, 2, 3])->toBeList()
        ->and(['a' => 1, 'b' => 2, 'c' => 3])->not->toBeList()
        ->and('1, 2, 3')->not->toBeList();
});

test('failures', function (): void {
    expect()->toBeList();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect()->toBeList('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(['a', 'b', 'c'])->not->toBeList();
})->throws(ExpectationFailedException::class);
