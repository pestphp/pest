<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('abc')->toBeSnakeCase()
        ->and('abc_def')->toBeSnakeCase()
        ->and('abc-def')->not->toBeSnakeCase()
        ->and('abcDef')->not->toBeSnakeCase()
        ->and('AbcDef')->not->toBeSnakeCase();
});

test('failures', function (): void {
    expect('Abc')->toBeSnakeCase();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('Abc')->toBeSnakeCase('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('abc_def')->not->toBeSnakeCase();
})->throws(ExpectationFailedException::class);
