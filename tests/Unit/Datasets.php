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

$state       = new stdClass();
$state->combinations = [
    ['a', 'b', 'c', 1, 2, 'bar', 'foo', 'baz'],
    ['a', 'b', 'c', 1, 2, 'zip', 'zap', 'zop'],
    ['a', 'b', 'c', 3, 4, 'bar', 'foo', 'baz'],
    ['a', 'b', 'c', 3, 4, 'zip', 'zap', 'zop'],
    ['a', 'b', 'c', 5, 6, 'bar', 'foo', 'baz'],
    ['a', 'b', 'c', 5, 6, 'zip', 'zap', 'zop'],
    ['d', 'e', 'f', 1, 2, 'bar', 'foo', 'baz'],
    ['d', 'e', 'f', 1, 2, 'zip', 'zap', 'zop'],
    ['d', 'e', 'f', 3, 4, 'bar', 'foo', 'baz'],
    ['d', 'e', 'f', 3, 4, 'zip', 'zap', 'zop'],
    ['d', 'e', 'f', 5, 6, 'bar', 'foo', 'baz'],
    ['d', 'e', 'f', 5, 6, 'zip', 'zap', 'zop'],
    ['g', 'h', 'i', 1, 2, 'bar', 'foo', 'baz'],
    ['g', 'h', 'i', 1, 2, 'zip', 'zap', 'zop'],
    ['g', 'h', 'i', 3, 4, 'bar', 'foo', 'baz'],
    ['g', 'h', 'i', 3, 4, 'zip', 'zap', 'zop'],
    ['g', 'h', 'i', 5, 6, 'bar', 'foo', 'baz'],
    ['g', 'h', 'i', 5, 6, 'zip', 'zap', 'zop'],
];

it('generates a matrix with given datasets', function ($a1, $a2, $a3, $b1, $b2, $c1, $c2, $c3) use ($state) {
    $combinations = $state->combinations;
    $set = $combinations[0];
    array_shift($combinations);
    $state->combinations = $combinations;

    expect([$a1, $a2, $a3, $b1, $b2, $c1, $c2, $c3])->toMatchArray($set);
})->with([
    'dataset_aa' => ['a1' => 'a', 'a2' => 'b', 'a3' => 'c'],
    'dataset_ab' => ['a1' => 'd', 'a2' => 'e', 'a3' => 'f'],
    'dataset_ac' => ['a1' => 'g', 'a2' => 'h', 'a3' => 'i'],
])->with([
    'dataset_ba' => ['b1' => 1, 'b2' => 2],
    'dataset_bb' => ['b1' => 3, 'b2' => 4],
    'dataset_bc' => ['b1' => 5, 'b2' => 6],
])->with([
    ['c1' => 'bar', 'c2' => 'foo', 'c3' => 'baz'],
    ['c1' => 'zip', 'c2' => 'zap', 'c3' => 'zop'],
]);
