<?php

$state = new stdClass;
$state->text = '';
test('uses dataset', function ($value) use ($state): void {
    $state->text .= $value;
    expect(true)->toBeTrue();
})->with('numbers.array');

test('the right dataset is taken', function () use ($state): void {
    expect($state->text)->toBe('12');
});

it('can see datasets defined in Pest.php file', function (string $value) use ($state): void {
    $state->text .= $value;
    expect(true)->toBeTrue();
})->with('dataset_in_pest_file');

test('Pest.php dataset is taken', function () use ($state): void {
    expect($state->text)->toBe('12AB');
});
