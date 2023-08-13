<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('lowercase')->toBeLowercase();
    expect('UPPERCASE')->not->toBeLowercase();
});

test('failures', function () {
    expect('UPPERCASE')->toBeLowercase();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('UPPERCASE')->toBeLowercase('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect('lowercase')->not->toBeLowercase();
})->throws(ExpectationFailedException::class);
