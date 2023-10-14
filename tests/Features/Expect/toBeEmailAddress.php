<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('hello@pest.com')->toBeEmailAddress()
        ->and('pestphp.com')->not->toBeEmailAddress();
});

test('failures', function () {
    expect('pestphp.com')->toBeEmailAddress();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('pestphp.com')->toBeEmailAddress('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('failures with default message', function () {
    expect('pestphp.com')->toBeEmailAddress();
})->throws(ExpectationFailedException::class, 'Failed asserting that pestphp.com is an email address.');

test('not failures', function () {
    expect('hello@pest.com')->not->toBeEmailAddress();
})->throws(ExpectationFailedException::class);
