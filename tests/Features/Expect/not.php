<?php

test('not property calls', function () {
    expect(true)
        ->toBeTrue()
        ->not()->toBeFalse()
        ->not->toBeFalse
        ->not(true)->toBeFalse()
        ->not(false)->toBeTrue()
        ->and(false)
        ->toBeFalse();
});
