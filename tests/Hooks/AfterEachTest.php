<?php

uses()->afterEach(function () {
    expect($this)
        ->toHaveProperty('ith')
        ->and($this->ith)
        ->toBe(0);

    $this->ith = 1;
});

afterEach(function () {
    expect($this)
        ->toHaveProperty('ith')
        ->and($this->ith)
        ->toBe(1);
});

test('global afterEach execution order', function () {
    expect($this)
        ->not()
        ->toHaveProperty('ith');
});

