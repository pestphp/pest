<?php

$state = new stdClass();
$state->text = '';
test('uses dataset', function ($value) use ($state) {
    $state->text .= $value;
    expect(true)->toBe(true);
})->with('numbers.array');

test('the right dataset is taken', function () use ($state) {
    expect($state->text)->toBe('12');
});

it('can see datasets defined in Pest.php file', function (string $value) use ($state) {
    $state->text .= $value;
    expect(true)->toBe(true);
})->with('dataset_in_pest_file');

test('Pest.php dataset is taken', function () use ($state) {
    expect($state->text)->toBe('12AB');
});

it('can see datasets defined in Pest.php file 2', function (string $value) use ($state) {
    $state->text .= $value;
    expect(true)->toBe(true);
})->with('nested.letters');

test('Pest.php dataset is taken 2', function () use ($state) {
    expect($state->text)->toBe('12ABABC');
});

test('uses datasets in nested directories', function ($value) use ($state) {
    $state->text .= $value;
    expect(true)->toBe(true);
})->with('nested.letters');

test('nested dataset is taken', function () use ($state) {
    expect($state->text)->toBe('12ABABABC');
});
