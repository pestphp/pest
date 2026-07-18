<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('1.1')->toBeString()
        ->and(1.1)->not->toBeString();
});

test('failures', function (): void {
    expect()->toBeString();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect()->toBeString('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('42')->not->toBeString();
})->throws(ExpectationFailedException::class);
