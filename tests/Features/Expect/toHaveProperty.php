<?php

use PHPUnit\Framework\ExpectationFailedException;

$obj = new stdClass;
$obj->foo = 'bar';
$obj->fooNull = null;

test('pass', function () use ($obj) {
    expect($obj)->toHaveProperty('foo');
    expect($obj)->toHaveProperty('foo', 'bar');
    expect($obj)->toHaveProperty('fooNull');
    expect($obj)->toHaveProperty('fooNull', null);
});

test('failures', function () use ($obj) {
    expect($obj)->toHaveProperty('bar');
})->throws(ExpectationFailedException::class);

test('failures with message', function () use ($obj) {
    expect($obj)->toHaveProperty(name: 'bar', message: 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('failures with message and Any matcher', function () use ($obj) {
    expect($obj)->toHaveProperty('bar', expect()->any(), 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () use ($obj) {
    expect($obj)->not->toHaveProperty('foo');
})->throws(ExpectationFailedException::class);
