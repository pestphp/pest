<?php

use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function () {
    $this->whenObject = new stdClass;
    $this->whenObject->trueValue = true;
    $this->whenObject->foo = 'foo';
});

it('pass', function () {
    expect('foo')
        ->when(
            true,
            function ($value) {
                return $value->toEqual('foo');
            }
        )
        ->toEqual('foo');

    expect(static::getCount())->toBe(2);
});

it('failures', function () {
    expect('foo')
        ->when(
            true,
            function ($value) {
                return $value->toBeTrue();
            }
        )
        ->toEqual('foo');
})->throws(ExpectationFailedException::class, 'is true');

it('runs with truthy', function () {
    expect($this->whenObject)
        ->when(
            1,
            function ($value) {
                return $value->trueValue->toBeTrue();
            }
        )
        ->foo->toEqual('foo');

    expect(static::getCount())->toBe(2);
});

it('skips with falsy', function () {
    expect($this->whenObject)
        ->when(
            0,
            function ($value) {
                return $value->trueValue->toBeFalse(); // fails
            }
        )
        ->when(
            false,
            function ($value) {
                return $value->trueValue->toBeFalse(); // fails
            }
        )
        ->foo->toEqual('foo');

    expect(static::getCount())->toBe(1);
});

it('runs with truthy closure condition', function () {
    expect($this->whenObject)
        ->when(
            function () {
                return '1';
            },
            function ($value) {
                return $value->trueValue->toBeTrue();
            }
        )
        ->foo->toEqual('foo');

    expect(static::getCount())->toBe(2);
});

it('skips with falsy closure condition', function () {
    expect($this->whenObject)
        ->when(
            function () {
                return '0';
            },
            function ($value) {
                return $value->trueValue->toBeFalse(); // fails
            }
        )
        ->foo->toEqual('foo');

    expect(static::getCount())->toBe(1);
});

it('can be used in higher order tests')
    ->expect(false)
    ->when(
        function () {
            return true;
        },
        function ($value) {
            return $value->toBeTrue();
        }
    )
    ->throws(ExpectationFailedException::class, 'false is true');
