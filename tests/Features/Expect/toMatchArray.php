<?php

use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function () {
    $this->user = [
        'id' => 1,
        'name' => 'Nuno',
        'email' => 'enunomaduro@gmail.com',
    ];
});

test('pass', function () {
    expect($this->user)->toMatchArray([
        'name' => 'Nuno',
        'email' => 'enunomaduro@gmail.com',
    ]);
});

test('failures', function () {
    expect($this->user)->toMatchArray([
        'name' => 'Not the same name',
        'email' => 'enunomaduro@gmail.com',
    ]);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect($this->user)->toMatchArray([
        'name' => 'Not the same name',
        'email' => 'enunomaduro@gmail.com',
    ], 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect($this->user)->not->toMatchArray([
        'id' => 1,
    ]);
})->throws(ExpectationFailedException::class);

test('failures with default message', function () {
    expect($this->user)->toMatchArray([
        'id' => 1,
        'email' => 'enunomaduro@gmall.com',
    ]);
})->throws(ExpectationFailedException::class, 'Failed asserting that an array has a key \'email\' with the value \'enunomaduro@gmail.com\'');
