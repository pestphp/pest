<?php

test('the slowest parallel test', function (): void {
    expect(true)->toBeTrue();
});

test('the fastest parallel test', function (): void {
    expect(true)->toBeTrue();
});
