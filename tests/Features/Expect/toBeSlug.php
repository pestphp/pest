<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('This is a Test String!')->toBeSlug()
        ->and('Another Test String')->toBeSlug();
});

test('failures', function () {
    expect('')->toBeSlug();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('')->toBeSlug('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('failures with default message', function () {
    expect('')->toBeSlug();
})->throws(ExpectationFailedException::class, 'Failed asserting that  can be converted to a slug.');

test('not failures', function () {
    expect('This is a Test String!')->not->toBeSlug();
})->throws(ExpectationFailedException::class);
