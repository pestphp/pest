<?php

test('missing dataset', function (string $value) {
    expect($value)->toBe('x');
})->with('missing.dataset');
