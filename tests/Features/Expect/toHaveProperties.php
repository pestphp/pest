<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    $object = new stdClass;
    $object->name = 'John';
    $object->age = 21;

    expect($object)
        ->toHaveProperties(['name', 'age'])
        ->toHaveProperties([
            'name' => 'John',
            'age' => 21,
        ]);
});

test('failures', function () {
    $object = new stdClass;
    $object->name = 'John';

    expect($object)
        ->toHaveProperties(['name', 'age'])
        ->toHaveProperties([
            'name' => 'John',
            'age' => 21,
        ]);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    $object = new stdClass;
    $object->name = 'John';

    expect($object)
        ->toHaveProperties(['name', 'age'], 'oh no!')
        ->toHaveProperties([
            'name' => 'John',
            'age' => 21,
        ], 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    $object = new stdClass;
    $object->name = 'John';
    $object->age = 21;

    expect($object)->not->toHaveProperties(['name', 'age'])
        ->not->toHaveProperties([
            'name' => 'John',
            'age' => 21,
        ]);
})->throws(ExpectationFailedException::class);
