<?php

it('ensures that the values returned for the index are correct', function () {
    $array = [1, 2, 3, 4];

    expect($array)
        ->at(0)->toBe(1)
        ->and($array)
        ->at(1)->toBe(2)
        ->and($array)
        ->at(2)->toBe(3)
        ->and($array)
        ->at(3)->toBe(4);
});

it('ensures that the values returned for the key are correct', function () {
    $dictionary = [
        'first_name' => 'John',
        'last_name' => 'Doe',
    ];

    expect($dictionary)
        ->at('first_name')->toBe('John')
        ->and($dictionary)
        ->at('last_name')->toBe('Doe');
});

it('ensures it work with nested array', function () {
    $nestedArray = [
        [1, 2, 3],
        ['foo' => 'bar', 'john' => 'doe'],
    ];

    expect($nestedArray)
        ->at(0)->at(1)->toBe(2)
        ->and($nestedArray)
        ->at(1)->at('foo')->toBe('bar');
});

it('ensures it work with nested dictionary', function () {
    $nestedDictionary = [
        'foo' => [
            'bar' => [
                'john' => 'doe',
            ],
        ],
    ];

    expect($nestedDictionary)
        ->foo->at('bar')->at('john')->toBe('doe');
});

it('ensures it work with dot notation', function () {
    $nestedDictionary = [
        'foo' => [
            'bar' => [
                'john' => 'doe',
            ],
        ],
    ];

    expect($nestedDictionary)
        ->at('foo.bar.john')->toBe('doe');
});

it('ensures it return an out of range exception', function () {
    $array = [1, 2, 3, 4];

    expect($array)->at(4);
})->throws('Index out of range: 4');

it('ensures it throw an invalid expectation value', function () {
    $boolean = false;

    expect($boolean)->at(1);
})->throws('Invalid expectation value type. Expected [iterable].');
