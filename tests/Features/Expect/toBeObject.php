<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect((object) ['a' => 1])->toBeObject();
    expect(['a' => 1])->not->toBeObject();
});

test('failures', function () {
    expect(null)->toBeObject();
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect((object) 'ciao')->not->toBeObject();
})->throws(ExpectationFailedException::class);
