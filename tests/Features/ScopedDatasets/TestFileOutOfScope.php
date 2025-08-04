<?php

$state = new stdClass;
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
