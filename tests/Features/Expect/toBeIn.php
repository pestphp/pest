<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('passes', function (): void {
    expect('a')->toBeIn(['a', 'b', 'c'])
        ->and('d')->not->toBeIn(['a', 'b', 'c']);
});

test('failures', function (): void {
    expect('d')->toBeIn(['a', 'b', 'c']);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('d')->toBeIn(['a', 'b', 'c'], 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('a')->not->toBeIn(['a', 'b', 'c']);
})->throws(ExpectationFailedException::class);
