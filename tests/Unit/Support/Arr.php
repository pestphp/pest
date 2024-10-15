<?php

use Pest\Support\Arr;

describe('last', function () {
    it('should return false for an empty arary', function () {
        expect(Arr::last([]))->toBeFalse();
    });

    it('should return the last element for an array with a single element', function () {
        expect(Arr::last([1]))->toBe(1);
    });

    it('should return the last element for an array without changing the internal pointer', function () {
        $array = [1, 2, 3];

        expect(Arr::last($array))->toBe(3);
        expect(current($array))->toBe(1);

        next($array);
        expect(current($array))->toBe(2);
        expect(Arr::last($array))->toBe(3);
        expect(current($array))->toBe(2);
    });

    it('should return the last element for an associative array without changing the internal pointer', function () {
        $array = ['first' => 1, 'second' => 2, 'third' => 3];

        expect(Arr::last($array))->toBe(3);
        expect(current($array))->toBe(1);

        next($array);
        expect(current($array))->toBe(2);
        expect(Arr::last($array))->toBe(3);
        expect(current($array))->toBe(2);
    });

    it('should return the last element for an mixed key array without changing the internal pointer', function () {
        $array = ['first' => 1, 2, 'third' => 3];

        expect(Arr::last($array))->toBe(3);
        expect(current($array))->toBe(1);

        next($array);
        expect(current($array))->toBe(2);
        expect(Arr::last($array))->toBe(3);
        expect(current($array))->toBe(2);
    });
});
