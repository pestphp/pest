<?php

use Pest\Datasets;

it('show only the names of named datasets in their description', function () {
    $descriptions = array_keys(Datasets::resolve('test description', [
        'one' => [1],
        'two' => [[2]],
    ]));

    expect($descriptions[0])->toBe('test description with data set "one"');
    expect($descriptions[1])->toBe('test description with data set "two"');
});

it('show the actual dataset of non-named datasets in their description', function () {
    $descriptions = array_keys(Datasets::resolve('test description', [
        [1],
        [[2]],
    ]));

    expect($descriptions[0])->toBe('test description with (1)');
    expect($descriptions[1])->toBe('test description with (array(2))');
});
