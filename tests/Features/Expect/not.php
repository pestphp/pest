<?php

test('not property calls', function (): void {
    expect(true)
        ->toBeTrue()
        ->not()->toBeFalse()
        ->not->toBeFalse
        ->and(false)
        ->toBeFalse();
});
