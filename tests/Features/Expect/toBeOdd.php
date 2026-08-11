<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('passes with positive odd', function (): void {
    expect(3)->toBeOdd();
});

test('passes with negative odd', function (): void {
    expect(-7)->toBeOdd();
});

test('failure with even', function (): void {
    expect(4)->toBeOdd();
})->throws(ExpectationFailedException::class);

test('failure with zero', function (): void {
    expect(0)->toBeOdd();
})->throws(ExpectationFailedException::class);

test('failure with custom message', function (): void {
    expect(4)->toBeOdd('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not', function (): void {
    expect(4)->not->toBeOdd();
});

test('not failure', function (): void {
    expect(3)->not->toBeOdd();
})->throws(ExpectationFailedException::class);
