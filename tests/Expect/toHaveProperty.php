<?php

use PHPUnit\Framework\ExpectationFailedException;

$obj      = new stdClass();
$obj->foo = 'bar';

test('pass', function () use ($obj) {
    expect($obj)->toHaveProperty('foo');
});

test('failures', function () use ($obj) {
    expect($obj)->toHaveProperty('bar');
})->throws(ExpectationFailedException::class);

test('not failures', function () use ($obj) {
    expect($obj)->not->toHaveProperty('foo');
})->throws(ExpectationFailedException::class);
