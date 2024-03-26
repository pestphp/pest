<?php

use Pest\Repositories\DatasetsRepository;

it('show only the names of named datasets in their description', function () {
    $descriptions = array_keys(DatasetsRepository::resolve([
        [
            'one' => [1],
            'two' => [[2]],
        ],
    ], __FILE__));

    expect($descriptions[0])->toBe('dataset "one"')
        ->and($descriptions[1])->toBe('dataset "two"');
});

it('show the actual dataset of non-named datasets in their description', function () {
    $descriptions = array_keys(DatasetsRepository::resolve([
        [
            [1],
            [[2]],
        ],
    ], __FILE__));

    expect($descriptions[0])->toBe('(1)');
    expect($descriptions[1])->toBe('([2])');
});

it('show only the names of multiple named datasets in their description', function () {
    $descriptions = array_keys(DatasetsRepository::resolve([
        [
            'one' => [1],
            'two' => [[2]],
        ],
        [
            'three' => [3],
            'four' => [[4]],
        ],
    ], __FILE__));

    expect($descriptions[0])->toBe('dataset "one" / dataset "three"');
    expect($descriptions[1])->toBe('dataset "one" / dataset "four"');
    expect($descriptions[2])->toBe('dataset "two" / dataset "three"');
    expect($descriptions[3])->toBe('dataset "two" / dataset "four"');
});

it('show the actual dataset of multiple non-named datasets in their description', function () {
    $descriptions = array_keys(DatasetsRepository::resolve([
        [
            [1],
            [[2]],
        ],
        [
            [3],
            [[4]],
        ],
    ], __FILE__));

    expect($descriptions[0])->toBe('(1) / (3)');
    expect($descriptions[1])->toBe('(1) / ([4])');
    expect($descriptions[2])->toBe('([2]) / (3)');
    expect($descriptions[3])->toBe('([2]) / ([4])');
});

it('show the correct description for mixed named and not-named datasets', function () {
    $descriptions = array_keys(DatasetsRepository::resolve([
        [
            'one' => [1],
            [[2]],
        ],
        [
            [3],
            'four' => [[4]],
        ],
    ], __FILE__));

    expect($descriptions[0])->toBe('dataset "one" / (3)');
    expect($descriptions[1])->toBe('dataset "one" / dataset "four"');
    expect($descriptions[2])->toBe('([2]) / (3)');
    expect($descriptions[3])->toBe('([2]) / dataset "four"');
});

it('shows the correct description for long texts with newlines', function () {
    $descriptions = array_keys(DatasetsRepository::resolve([
        [
            ['some very \nlong text with \n     newlines'],
        ],
    ], __FILE__));

    expect($descriptions[0])->toBe('(\'some very long text with …wlines\')');
});

it('shows the correct description for arrays with many elements', function () {
    $descriptions = array_keys(DatasetsRepository::resolve([
        [
            [[1, 2, 3, 4, 5]],
        ],
    ], __FILE__));

    expect($descriptions[0])->toBe('([1, 2, 3, …])');
});

it('shows the correct description of datasets with html', function () {
    $descriptions = array_keys(DatasetsRepository::resolve([
        [
            '<div class="flex items-center"></div>',
        ],
    ], __FILE__));

    expect($descriptions[0])->toBe('(\'<div class="flex items-center"></div>\')');
});
