<?php

use Pest\Datasets;

it('show the names of named datasets in their description', function () {
    $descriptions = array_keys(Datasets::resolve('test description', [
        'one' => [1],
        'two' => [[2]],
    ]));

    expect($descriptions[0])->toBe('test description with data set "one" (1)');
    expect($descriptions[1])->toBe('test description with data set "two" (array(2))');
});
