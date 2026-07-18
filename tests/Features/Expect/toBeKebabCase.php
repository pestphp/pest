<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('abc')->toBeKebabCase()
        ->and('abc-def')->toBeKebabCase()
        ->and('abc_def')->not->toBeKebabCase()
        ->and('abcDef')->not->toBeKebabCase()
        ->and('AbcDef')->not->toBeKebabCase();
});

test('failures', function (): void {
    expect('Abc')->toBeKebabCase();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('Abc')->toBeKebabCase('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('abc-def')->not->toBeKebabCase();
})->throws(ExpectationFailedException::class);
