<?php

$state = new stdClass;
$state->text = '';

it('can see datasets in nested Dataset folders', function ($value) use ($state) {
    $state->text .= $value;
    expect(true)->toBeTrue();
})->with('nested.letters');

test('Nested dataset is taken', function () use ($state) {
    expect($state->text)->toBe('AB');
});
