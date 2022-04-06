<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    $object       = new stdClass();
    $object->name = 'Jhon';
    $object->age  = 21;

    expect($object)->toHaveProperties(['name', 'age']);
});

test('failures', function () {
    $object       = new stdClass();
    $object->name = 'Jhon';

    expect($object)->toHaveProperties(['name', 'age']);
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    $object       = new stdClass();
    $object->name = 'Jhon';
    $object->age  = 21;

    expect($object)->not->toHaveProperties(['name', 'age']);
})->throws(ExpectationFailedException::class);
