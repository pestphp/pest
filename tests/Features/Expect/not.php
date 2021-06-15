<?php

test('not property calls', function () {
    expect(true)
        ->toBeTrue()
        ->not()->toBeFalse()
        ->not->toBeFalse
        ->and(false)
        ->toBeFalse();
});
