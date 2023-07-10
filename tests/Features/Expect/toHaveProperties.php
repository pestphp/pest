<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass objects', function () {
    $object = new stdClass();
    $object->name = 'John';
    $object->age = 21;

    expect($object)
        ->toHaveProperties(['name', 'age'])
        ->toHaveProperties([
            'name' => 'John',
            'age' => 21,
        ]);

    $nested_object = new stdClass();
    $nested_object->name = 'John';
    $nested_object->age = 21;
    $nested_object->address = new stdClass();
    $nested_object->address->street = '123 Main St.';
    $nested_object->address->city = 'Melbourne';
    $nested_object->address->state = 'VIC';
    $nested_object->address->zip = '3000';

    expect($nested_object)
        ->toHaveProperties(['name', 'age', 'address'])
        ->toHaveProperties([
            'name' => 'John',
            'age' => 21,
            'address' => [
                'street' => '123 Main St.',
                'city' => 'Melbourne',
                'state' => 'VIC',
                'zip' => '3000',
            ],
        ]);
});

test('pass arrays', function () {
    $array = [
        'name' => 'John',
        'age' => 21,
    ];

    expect($array)
        ->toHaveProperties(['name', 'age'])
        ->toHaveProperties([
            'name' => 'John',
            'age' => 21,
        ]);

    $nested_array = [
        'name' => 'John',
        'age' => 21,
        'address' => [
            'street' => '123 Main St.',
            'city' => 'Melbourne',
            'state' => 'VIC',
            'zip' => '3000',
        ],
    ];

    expect($nested_array)
        ->toHaveProperties(['name', 'age', 'address'])
        ->toHaveProperties([
            'name' => 'John',
            'age' => 21,
            'address' => [
                'street' => '123 Main St.',
                'city' => 'Melbourne',
                'state' => 'VIC',
                'zip' => '3000',
            ],
        ]);
});

test('failures objects', function () {
    $object = new stdClass();
    $object->name = 'John';

    expect($object)
        ->toHaveProperties(['name', 'age'])
        ->toHaveProperties([
            'name' => 'John',
            'age' => 21,
        ]);
})->throws(ExpectationFailedException::class);

test('failures nested objects', function () {
    $nested_object = new stdClass();
    $nested_object->name = 'John';
    $nested_object->address = new stdClass();
    $nested_object->address->street = '123 Main St.';
    $nested_object->address->city = 'Melbourne';
    $nested_object->address->state = 'VIC';

    expect($nested_object)
        ->toHaveProperties(['name', 'age', 'address'])
        ->toHaveProperties([
            'name' => 'John',
            'age' => 21,
            'address' => [
                'street' => '123 Main St.',
                'city' => 'Melbourne',
                'state' => 'VIC',
                'zip' => '3000',
            ],
        ]);
})->throws(ExpectationFailedException::class);

test('failures arrays', function () {
    $array = [
        'name' => 'John',
    ];

    expect($array)
        ->toHaveProperties(['name', 'age'])
        ->toHaveProperties([
            'name' => 'John',
            'age' => 21,
        ]);
})->throws(ExpectationFailedException::class);

test('failures nested arrays', function () {
    $nested_array = [
        'name' => 'John',
        'address' => [
            'street' => '123 Main St.',
            'city' => 'Melbourne',
            'zip' => '3000',
        ],
    ];

    expect($nested_array)
        ->toHaveProperties(['name', 'age', 'address'])
        ->toHaveProperties([
            'name' => 'John',
            'age' => 21,
            'address' => [
                'street' => '123 Main St.',
                'city' => 'Melbourne',
                'state' => 'VIC',
                'zip' => '3000',
            ],
        ]);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    $object = new stdClass();
    $object->name = 'John';

    expect($object)
        ->toHaveProperties(['name', 'age'], 'oh no!')
        ->toHaveProperties([
            'name' => 'John',
            'age' => 21,
        ], 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures objects', function () {
    $object = new stdClass();
    $object->name = 'John';
    $object->age = 21;

    expect($object)->not->toHaveProperties(['name', 'age'])
        ->not->toHaveProperties([
            'name' => 'John',
            'age' => 21,
        ]);
})->throws(ExpectationFailedException::class);

test('not failures arrays', function () {
    $array = [
        'name' => 'John',
        'age' => 21,
    ];

    expect($array)->not->toHaveProperties(['name', 'age'])
        ->not->toHaveProperties([
            'name' => 'John',
            'age' => 21,
        ]);
})->throws(ExpectationFailedException::class);

test('not failures nested object', function () {
    $nested_object = new stdClass();
    $nested_object->name = 'John';
    $nested_object->age = 21;
    $nested_object->address = new stdClass();
    $nested_object->address->street = '123 Main St.';
    $nested_object->address->city = 'Melbourne';
    $nested_object->address->state = 'VIC';
    $nested_object->address->zip = '3000';

    expect($nested_object)->not->toHaveProperties(['name', 'age', 'address'])
        ->not->toHaveProperties([
            'name' => 'John',
            'age' => 21,
            'address' => [
                'street' => '123 Main St.',
                'city' => 'Melbourne',
                'state' => 'VIC',
                'zip' => '3000',
            ],
        ]);
})->throws(ExpectationFailedException::class);

test('not failures nested arrays', function () {
    $nested_array = [
        'name' => 'John',
        'age' => 21,
        'address' => [
            'street' => '123 Main St.',
            'city' => 'Melbourne',
            'state' => 'VIC',
            'zip' => '3000',
        ],
    ];

    expect($nested_array)->not->toHaveProperties(['name', 'age', 'address'])
        ->not->toHaveProperties([
            'name' => 'John',
            'age' => 21,
            'address' => [
                'street' => '123 Main St.',
                'city' => 'Melbourne',
                'state' => 'VIC',
                'zip' => '3000',
            ],
        ]);
})->throws(ExpectationFailedException::class);
