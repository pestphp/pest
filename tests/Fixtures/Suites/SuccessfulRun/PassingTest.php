<?php

declare(strict_types=1);

test('passes', function (): void {
    expect(1 + 1)->toBe(2);
});

describe('dataset', function (): void {
    test('keeps its label', function (string $value): void {
        expect($value)->toBe('value');
    })->with(['named case' => ['value']]);
});
