<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('Hello World')->toMatch('/^hello wo.*$/i');
});

test('failures', function (): void {
    expect('Hello World')->toMatch('/^hello$/i');
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('Hello World')->toMatch('/^hello$/i', 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('Hello World')->not->toMatch('/^hello wo.*$/i');
})->throws(ExpectationFailedException::class);
