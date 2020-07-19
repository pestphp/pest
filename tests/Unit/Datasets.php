<?php

use Pest\Datasets;

it('show the names of named datasets in their description', function () {
    $descriptions = array_keys(Datasets::resolve('test description', [
        'one' => [1],
        'two' => [[2]],
    ]));

    $this->assertSame('test description with data set "one" (1)', $descriptions[0]);
    $this->assertSame('test description with data set "two" (array(2))', $descriptions[1]);
});
