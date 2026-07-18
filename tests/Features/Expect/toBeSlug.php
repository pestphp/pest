<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function (): void {
    expect('This is a Test String!')->toBeSlug()
        ->and('Another Test String')->toBeSlug();
});

test('failures', function (): void {
    expect('')->toBeSlug();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect('')->toBeSlug('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('failures with default message', function (): void {
    expect('')->toBeSlug();
})->throws(ExpectationFailedException::class, 'Failed asserting that  can be converted to a slug.');

test('not failures', function (): void {
    expect('This is a Test String!')->not->toBeSlug();
})->throws(ExpectationFailedException::class);
