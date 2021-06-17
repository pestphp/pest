<?php

use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function () {
    $this->user = (object) [
        'id'    => 1,
        'name'  => 'Nuno',
        'email' => 'enunomaduro@gmail.com',
    ];
});

test('pass', function () {
    expect($this->user)->toMatchObject([
        'name'  => 'Nuno',
        'email' => 'enunomaduro@gmail.com',
    ]);
});

test('failures', function () {
    expect($this->user)->toMatchObject([
        'name'  => 'Not the same name',
        'email' => 'enunomaduro@gmail.com',
    ]);
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect($this->user)->not->toMatchObject([
        'id' => 1,
    ]);
})->throws(ExpectationFailedException::class);
