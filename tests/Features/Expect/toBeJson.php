<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('{"hello":"world"}')->toBeJson()
        ->and('foo')->not->toBeJson()
        ->and('{"hello"')->not->toBeJson();
});

test('failures', function (): void {
    expect(':"world"}')->toBeJson();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(':"world"}')->toBeJson('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect('{"hello":"world"}')->not->toBeJson();
})->throws(ExpectationFailedException::class);
