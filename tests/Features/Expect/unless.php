<?php

use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function () {
    $this->unlessObject = new stdClass;
    $this->unlessObject->trueValue = true;
    $this->unlessObject->foo = 'foo';
});

it('pass', function () {
    expect('foo')
        ->unless(
            true,
            function ($value) {
                return $value->toEqual('bar');
            }
        )
        ->toEqual('foo');

    expect(static::getCount())->toBe(1);
});

it('failures', function () {
    expect('foo')
        ->unless(
            false,
            function ($value) {
                return $value->toBeTrue();
            }
        )
        ->toEqual('foo');
})->throws(ExpectationFailedException::class, 'is true');

it('runs with truthy', function () {
    expect($this->unlessObject)
        ->unless(
            0,
            function ($value) {
                return $value->trueValue->toBeTrue();
            }
        )
        ->foo->toEqual('foo');

    expect(static::getCount())->toBe(2);
});

it('skips with falsy', function () {
    expect($this->unlessObject)
        ->unless(
            1,
            function ($value) {
                return $value->trueValue->toBeFalse(); // fails
            }
        )
        ->unless(
            true,
            function ($value) {
                return $value->trueValue->toBeFalse(); // fails
            }
        )
        ->foo->toEqual('foo');

    expect(static::getCount())->toBe(1);
});

it('runs with truthy closure condition', function () {
    expect($this->unlessObject)
        ->unless(
            function () {
                return '0';
            },
            function ($value) {
                return $value->trueValue->toBeTrue();
            }
        )
        ->foo->toEqual('foo');

    expect(static::getCount())->toBe(2);
});

it('skips with falsy closure condition', function () {
    expect($this->unlessObject)
        ->unless(
            function () {
                return '1';
            },
            function ($value) {
                return $value->trueValue->toBeFalse(); // fails
            }
        )
        ->foo->toEqual('foo');

    expect(static::getCount())->toBe(1);
});

it('can be used in higher order tests')
    ->expect(true)
    ->unless(
        function () {
            return false;
        },
        function ($value) {
            return $value->toBeFalse();
        }
    )
    ->throws(ExpectationFailedException::class, 'true is false');
