<?php

pest()->beforeEach(function (): void {
    expect($this)
        ->toHaveProperty('baz')
        ->and($this->baz)
        ->toBe(1);

    $this->baz = 2;
});

beforeEach(function (): void {
    expect($this)
        ->toHaveProperty('baz')
        ->and($this->baz)
        ->toBe(2);

    $this->baz = 3;
});

test('global beforeEach execution order', function (): void {
    expect($this)
        ->toHaveProperty('baz')
        ->and($this->baz)
        ->toBe(3);
});
