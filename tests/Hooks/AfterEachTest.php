<?php

beforeEach(function () {
    $this->ith = 0;
});

pest()->afterEach(function () {
    expect($this)
        ->toHaveProperty('ith')
        ->and($this->ith)
        ->toBe(3);

    $this->ith++;
});

pest()->afterEach(function () {
    expect($this)
        ->toHaveProperty('ith')
        ->and($this->ith)
        ->toBe(4);

    $this->ith++;
});

afterEach(function () {
    expect($this)
        ->toHaveProperty('ith')
        ->and($this->ith)
        ->toBe(5);

    $this->ith++;
});

describe('nested', function () {
    afterEach(function () {
        expect($this)
            ->toHaveProperty('ith')
            ->and($this->ith)
            ->toBe(6);

        $this->ith++;
    });

    test('nested afterEach execution order', function () {
        expect($this)
            ->toHaveProperty('ith')
            ->and($this->ith)
            ->toBe(0);

        $this->ith++;
    });

    afterEach(function () {
        expect($this)
            ->toHaveProperty('ith')
            ->and($this->ith)
            ->toBe(7);

        $this->ith++;
    });
});

afterEach(function () {
    expect($this)
        ->toHaveProperty('ith')
        ->and($this->ith)
        ->toBeBetween(6, 8);

    $this->ith++;
});

test('global afterEach execution order', function () {
    expect($this)
        ->toHaveProperty('ith')
        ->and($this->ith)
        ->toBe(0);

    $this->ith++;
});
