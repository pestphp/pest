<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('abc')->toBeCamelCase()
        ->and('abcDef')->toBeCamelCase()
        ->and('abc-def')->not->toBeCamelCase()->not->toBeCamelCase()
        ->and('AbcDef')->not->toBeCamelCase();
});

test('failures', function (): void {
    expect('Abc')->toBeCamelCase();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('Abc')->toBeCamelCase('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('abcDef')->not->toBeCamelCase();
})->throws(ExpectationFailedException::class);
