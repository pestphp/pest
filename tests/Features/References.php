<?php

use Pest\Panic;

it('can reference a specific class', function (): void {
    expect(Panic::class)->toBeString();
})->references(Panic::class);

it('can reference a specific class method', function (): void {
    expect(Panic::with(...))->toBeCallable();
})->references([Panic::class, 'with']);
