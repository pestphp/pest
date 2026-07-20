<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect(['name' => 'Nuno'])->toBeAssociative()
        ->and(['id' => 1, 'name' => 'Taylor'])->toBeAssociative()
        ->and(['foo' => 'bar'])->toBeAssociative()
        ->and([1, 2, 3])->not->toBeAssociative()
        ->and([])->not->toBeAssociative();
});

test('failures', function (): void {
    expect([1, 2, 3])->toBeAssociative();
})->throws(ExpectationFailedException::class);

test('failures with invalid type', function (): void {
    expect('foo')->toBeAssociative();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect([1, 2, 3])->toBeAssociative('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(['foo' => 'bar'])->not->toBeAssociative();
})->throws(ExpectationFailedException::class);
