<?php

use PHPUnit\Framework\ExpectationFailedException;

$obj          = new stdClass();
$obj->foo     = 'bar';
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

test('not failures', function () use ($obj) {
    expect($obj)->not->toHaveProperty('foo');
})->throws(ExpectationFailedException::class);
