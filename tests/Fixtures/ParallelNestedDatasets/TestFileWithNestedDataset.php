<?php

test('loads nested dataset', function (string $name) {
    expect($name)->not->toBeEmpty();
})->with('nested.users');
