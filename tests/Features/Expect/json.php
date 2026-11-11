<?php

use PHPUnit\Framework\ExpectationFailedException;

test('it properly parses json string', function (): void {
    expect('{"name":"Nuno"}')
        ->json()
        ->name
        ->toBe('Nuno');
});

test('fails with broken json string', function (): void {
    expect('{":"Nuno"}')->json();
})->throws(ExpectationFailedException::class);
