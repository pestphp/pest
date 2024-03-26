<?php

use PHPUnit\Framework\ExpectationFailedException;

$test_array = [
    'a' => 1,
    'b',
    'c' => 'world',
    'd' => [
        'e' => 'hello',
    ],
    'key.with.dots' => false,
];

test('pass')->expect($test_array)->toHaveKey('c');
test('pass with nested key')->expect($test_array)->toHaveKey('d.e');
test('pass with plain key with dots')->expect($test_array)->toHaveKey('key.with.dots');

test('pass with value check')->expect($test_array)->toHaveKey('c', 'world');
test('pass with value check and nested key')->expect($test_array)->toHaveKey('d.e', 'hello');
test('pass with value check and plain key with dots')->expect($test_array)->toHaveKey('key.with.dots', false);

test('failures', function () use ($test_array) {
    expect($test_array)->toHaveKey('foo');
})->throws(ExpectationFailedException::class, "Failed asserting that an array has the key 'foo'");

test('failures with custom message', function () use ($test_array) {
    expect($test_array)->toHaveKey('foo', message: 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('failures with custom message and Any matcher', function () use ($test_array) {
    expect($test_array)->toHaveKey('foo', expect()->any(), 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('failures with nested key', function () use ($test_array) {
    expect($test_array)->toHaveKey('d.bar');
})->throws(ExpectationFailedException::class, "Failed asserting that an array has the key 'd.bar'");

test('failures with nested key and custom message', function () use ($test_array) {
    expect($test_array)->toHaveKey('d.bar', message: 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('failures with nested key and custom message with Any matcher', function () use ($test_array) {
    expect($test_array)->toHaveKey('d.bar', expect()->any(), 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('failures with plain key with dots', function () use ($test_array) {
    expect($test_array)->toHaveKey('missing.key.with.dots');
})->throws(ExpectationFailedException::class, "Failed asserting that an array has the key 'missing.key.with.dots'");

test('fails with wrong value', function () use ($test_array) {
    expect($test_array)->toHaveKey('c', 'bar');
})->throws(ExpectationFailedException::class);

test('fails with wrong value and nested key', function () use ($test_array) {
    expect($test_array)->toHaveKey('d.e', 'foo');
})->throws(ExpectationFailedException::class);

test('fails with wrong value and plain key with dots', function () use ($test_array) {
    expect($test_array)->toHaveKey('key.with.dots', true);
})->throws(ExpectationFailedException::class);

test('not failures', function () use ($test_array) {
    expect($test_array)->not->toHaveKey('c');
})->throws(ExpectationFailedException::class, "Expecting […] not to have key 'c'");

test('not failures with nested key', function () use ($test_array) {
    expect($test_array)->not->toHaveKey('d.e');
})->throws(ExpectationFailedException::class, "Expecting […] not to have key 'd.e'");

test('not failures with plain key with dots', function () use ($test_array) {
    expect($test_array)->not->toHaveKey('key.with.dots');
})->throws(ExpectationFailedException::class, "Expecting […] not to have key 'key.with.dots'");

test('not failures with correct value', function () use ($test_array) {
    expect($test_array)->not->toHaveKey('c', 'world');
})->throws(ExpectationFailedException::class);

test('not failures with correct value and  with nested key', function () use ($test_array) {
    expect($test_array)->not->toHaveKey('d.e', 'hello');
})->throws(ExpectationFailedException::class);

test('not failures with correct value and  with plain key with dots', function () use ($test_array) {
    expect($test_array)->not->toHaveKey('key.with.dots', false);
})->throws(ExpectationFailedException::class);
