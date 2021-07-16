<?php

use PHPUnit\Framework\ExpectationFailedException;

test('it properly parses json string', function () {
    expect('{"name":"Nuno"}')
        ->json()
        ->name
        ->toBe('uno');
});

it('is incomplete');

it('doesnt do anything', function () {
});

test('fails with broken json string', function () {
    expect('{":"Nuno"}')->json();
})->throws(ExpectationFailedException::class);
