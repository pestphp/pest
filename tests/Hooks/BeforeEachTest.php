<?php

beforeEach(function (): void {
    expect($this->baz)->toBe(1); // set from Pest.php global/shared hook

    $this->baz = 2;
});

test('global before each', function (): void {
    expect($this->baz)->toBe(2);
});
