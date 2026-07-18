<?php

use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
    $this->unlessObject = new stdClass;
    $this->unlessObject->trueValue = true;
    $this->unlessObject->foo = 'foo';
});

it('pass', function (): void {
    expect('foo')
        ->unless(
            true,
            fn ($value) => $value->toEqual('bar')
        )
        ->toEqual('foo')
        ->and(static::getCount())->toBe(1);
});

it('failures', function (): void {
    expect('foo')
        ->unless(
            false,
            fn ($value) => $value->toBeTrue()
        )
        ->toEqual('foo');
})->throws(ExpectationFailedException::class, 'is true');

it('runs with truthy', function (): void {
    expect($this->unlessObject)
        ->unless(
            0,
            fn ($value) => $value->trueValue->toBeTrue()
        )
        ->foo->toEqual('foo')
        ->and(static::getCount())->toBe(2);
});

it('skips with falsy', function (): void {
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
        ->foo->toEqual('foo')
        ->and(static::getCount())->toBe(1);
});

it('runs with truthy closure condition', function (): void {
    expect($this->unlessObject)
        ->unless(
            fn (): string => '0',
            fn ($value) => $value->trueValue->toBeTrue()
        )
        ->foo->toEqual('foo')
        ->and(static::getCount())->toBe(2);
});

it('skips with falsy closure condition', function (): void {
    expect($this->unlessObject)
        ->unless(
            fn (): string => '1',
            function ($value) {
                return $value->trueValue->toBeFalse(); // fails
            }
        )
        ->foo->toEqual('foo')
        ->and(static::getCount())->toBe(1);
});

it('can be used in higher order tests')
    ->expect(true)
    ->unless(
        fn (): false => false,
        fn ($value) => $value->toBeFalse()
    )
    ->throws(ExpectationFailedException::class, 'true is false');
