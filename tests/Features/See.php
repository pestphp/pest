<?php

use Pest\Panic;

it('can reference a specific class', function (): void {
    expect(Panic::class)->toBeString();
})->see(Panic::class);

it('can reference a specific class method', function (): void {
    expect(Panic::with(...))->toBeCallable();
})->see([Panic::class, 'with']);
