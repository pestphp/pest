<?php

uses()->beforeEach(function () {
    expect($this)
        ->toHaveProperty('baz')
        ->and($this->baz)
        ->toBe(0);

    $this->baz = 1;
});

beforeEach(function () {
    expect($this)
        ->toHaveProperty('baz')
        ->and($this->baz)
        ->toBe(1);

    $this->baz = 2;
});

test('global beforeEach execution order', function () {
    expect($this)
        ->toHaveProperty('baz')
        ->and($this->baz)
        ->toBe(2);
});

