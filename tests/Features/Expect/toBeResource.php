<?php

use PHPUnit\Framework\ExpectationFailedException;

$resource = tmpfile();

afterAll(function () use ($resource) {
    fclose($resource);
});

test('pass', function () use ($resource) {
    expect($resource)->toBeResource();
    expect(null)->not->toBeResource();
});

test('failures', function () {
    expect(null)->toBeResource();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect(null)->toBeResource('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () use ($resource) {
    expect($resource)->not->toBeResource();
})->throws(ExpectationFailedException::class);
