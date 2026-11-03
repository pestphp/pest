<?php

test('covers nothing', function (): void {
    expect(true)->toBeTrue();
})->coversNothing();

test('declares no coverage metadata', function (): void {
    expect(true)->toBeTrue();
});
