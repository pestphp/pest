<?php

dataset('numbers.array', [
    1, 2, 3, 4, 5, 'ScopedDatasets/ScopedDatasets.php',
]);

$state = new stdClass;
$state->text = '';
test('uses dataset', function ($value) use ($state): void {
    $state->text .= $value;
    expect(true)->toBeTrue();
})->with('numbers.array');

test('the right dataset is taken', function () use ($state): void {
    expect($state->text)->toBe('12345ScopedDatasets/ScopedDatasets.php');
});
