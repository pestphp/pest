<?php

// Only ever run through `tests/Features/Tia.php`, against a seeded TIA graph.
// Both hooks throw, so a replayed test that wrongly runs one fails the run.

beforeEach(function (): void {
    throw new RuntimeException('The beforeEach hook must not run for replayed tests.');
});

afterEach(function (): void {
    throw new RuntimeException('The afterEach hook must not run for replayed tests.');
});

test('replayed pass', function (): void {
    expect(true)->toBeTrue();
});

test('replayed skip', function (): void {
    expect(true)->toBeTrue();
});

test('replayed incomplete', function (): void {
    expect(true)->toBeTrue();
});
