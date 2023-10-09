<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('https://pestphp.com')->toBeUrl()
        ->and('pestphp.com')->not->toBeUrl();
});

test('failures', function () {
    expect('pestphp.com')->toBeUrl();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('pestphp.com')->toBeUrl('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('failures with default message', function () {
    expect('pestphp.com')->toBeUrl();
})->throws(ExpectationFailedException::class, 'Failed asserting that pestphp.com is a url.');

test('not failures', function () {
    expect('https://pestphp.com')->not->toBeUrl();
})->throws(ExpectationFailedException::class);
