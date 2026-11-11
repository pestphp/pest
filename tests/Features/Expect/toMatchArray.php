<?php

use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
    $this->user = [
        'id' => 1,
        'name' => 'Nuno',
        'email' => 'enunomaduro@gmail.com',
    ];
});

test('pass', function (): void {
    expect($this->user)->toMatchArray([
        'name' => 'Nuno',
        'email' => 'enunomaduro@gmail.com',
    ]);
});

test('failures', function (): void {
    expect($this->user)->toMatchArray([
        'name' => 'Not the same name',
        'email' => 'enunomaduro@gmail.com',
    ]);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect($this->user)->toMatchArray([
        'name' => 'Not the same name',
        'email' => 'enunomaduro@gmail.com',
    ], 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect($this->user)->not->toMatchArray([
        'id' => 1,
    ]);
})->throws(ExpectationFailedException::class);
