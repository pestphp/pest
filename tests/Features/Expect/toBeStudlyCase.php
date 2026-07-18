<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('Abc')->toBeStudlyCase()
        ->and('AbcDef')->toBeStudlyCase()
        ->and('abc-def')->not->toBeStudlyCase()->not->toBeStudlyCase()
        ->and('abc')->not->toBeStudlyCase();
});

test('failures', function (): void {
    expect('abc')->toBeStudlyCase();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('abc')->toBeStudlyCase('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('AbcDef')->not->toBeStudlyCase();
})->throws(ExpectationFailedException::class);
