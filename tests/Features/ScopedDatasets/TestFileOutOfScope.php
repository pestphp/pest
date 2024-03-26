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


test('uses datasets in nested directories', function($value) use ($state){
    $state->text .= $value;
    expect(true)->toBe(true);
})->with('animals.dogs');
