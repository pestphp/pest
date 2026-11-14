<?php

use Tests\Fixtures\Covers\CoversClass1;

covers(CoversClass1::class);

test('covers the class', function (): void {
    expect(true)->toBeTrue();
});

test('covers nothing', function (): void {
    expect(true)->toBeTrue();
})->coversNothing();
