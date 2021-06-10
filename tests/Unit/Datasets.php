<?php

use Pest\Datasets;

it('show only the names of named datasets in their description', function () {
    $descriptions = array_keys(Datasets::resolve('test description', [
        [
            'one' => [1],
            'two' => [[2]],
        ],
    ]));

    expect($descriptions[0])->toBe('test description with data set "one"');
    expect($descriptions[1])->toBe('test description with data set "two"');
});

it('show the actual dataset of non-named datasets in their description', function () {
    $descriptions = array_keys(Datasets::resolve('test description', [
        [
            [1],
            [[2]],
        ],
    ]));

    expect($descriptions[0])->toBe('test description with (1)');
    expect($descriptions[1])->toBe('test description with (array(2))');
});

it('show only the names of multiple named datasets in their description', function () {
    $descriptions = array_keys(Datasets::resolve('test description', [
        [
            'one' => [1],
            'two' => [[2]],
        ],
        [
            'three' => [3],
            'four'  => [[4]],
        ],
    ]));

    expect($descriptions[0])->toBe('test description with data set "one" / data set "three"');
    expect($descriptions[1])->toBe('test description with data set "one" / data set "four"');
    expect($descriptions[2])->toBe('test description with data set "two" / data set "three"');
    expect($descriptions[3])->toBe('test description with data set "two" / data set "four"');
});

it('show the actual dataset of multiple non-named datasets in their description', function () {
    $descriptions = array_keys(Datasets::resolve('test description', [
        [
            [1],
            [[2]],
        ],
        [
            [3],
            [[4]],
        ],
    ]));

    expect($descriptions[0])->toBe('test description with (1) / (3)');
    expect($descriptions[1])->toBe('test description with (1) / (array(4))');
    expect($descriptions[2])->toBe('test description with (array(2)) / (3)');
    expect($descriptions[3])->toBe('test description with (array(2)) / (array(4))');
});

it('show the correct description for mixed named and not-named datasets', function () {
    $descriptions = array_keys(Datasets::resolve('test description', [
        [
            'one' => [1],
            [[2]],
        ],
        [
            [3],
            'four' => [[4]],
        ],
    ]));

    expect($descriptions[0])->toBe('test description with data set "one" / (3)');
    expect($descriptions[1])->toBe('test description with data set "one" / data set "four"');
    expect($descriptions[2])->toBe('test description with (array(2)) / (3)');
    expect($descriptions[3])->toBe('test description with (array(2)) / data set "four"');
});
