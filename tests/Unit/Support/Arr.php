<?php

declare(strict_types=1);

use Pest\Support\Arr;

describe('last', function (): void {
    it('should return false for an empty arary', function (): void {
        expect(Arr::last([]))->toBeFalse();
    });

    it('should return the last element for an array with a single element', function (): void {
        expect(Arr::last([1]))->toBe(1);
    });

    it('should return the last element for an array without changing the internal pointer', function (): void {
        $array = [1, 2, 3];

        expect(Arr::last($array))->toBe(3)
            ->and(current($array))->toBe(1);

        next($array);
        expect(current($array))->toBe(2)
            ->and(Arr::last($array))->toBe(3)
            ->and(current($array))->toBe(2);
    });

    it('should return the last element for an associative array without changing the internal pointer', function (): void {
        $array = ['first' => 1, 'second' => 2, 'third' => 3];

        expect(Arr::last($array))->toBe(3)
            ->and(current($array))->toBe(1);

        next($array);
        expect(current($array))->toBe(2)
            ->and(Arr::last($array))->toBe(3)
            ->and(current($array))->toBe(2);
    });

    it('should return the last element for an mixed key array without changing the internal pointer', function (): void {
        $array = ['first' => 1, 2, 'third' => 3];

        expect(Arr::last($array))->toBe(3)
            ->and(current($array))->toBe(1);

        next($array);
        expect(current($array))->toBe(2)
            ->and(Arr::last($array))->toBe(3)
            ->and(current($array))->toBe(2);
    });
});
