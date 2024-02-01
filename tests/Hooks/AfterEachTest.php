<?php

uses()->afterEach(function () {
    expect($this)
        ->toHaveProperty('ith')
        ->and($this->ith)
        ->toBe(1);

    $this->ith = 2;
});

afterEach(function () {
    expect($this)
        ->toHaveProperty('ith')
        ->and($this->ith)
        ->toBe(2);
});

test('global afterEach execution order', function () {
    expect($this)
        ->not()
        ->toHaveProperty('ith');
});
