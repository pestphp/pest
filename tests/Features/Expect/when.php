<?php

use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
    $this->whenObject = new stdClass;
    $this->whenObject->trueValue = true;
    $this->whenObject->foo = 'foo';
});

it('pass', function (): void {
    expect('foo')
        ->when(
            true,
            fn ($value) => $value->toEqual('foo')
        )
        ->toEqual('foo')
        ->and(static::getCount())->toBe(2);
});

it('failures', function (): void {
    expect('foo')
        ->when(
            true,
            fn ($value) => $value->toBeTrue()
        )
        ->toEqual('foo');
})->throws(ExpectationFailedException::class, 'is true');

it('runs with truthy', function (): void {
    expect($this->whenObject)
        ->when(
            1,
            fn ($value) => $value->trueValue->toBeTrue()
        )
        ->foo->toEqual('foo')
        ->and(static::getCount())->toBe(2);
});

it('skips with falsy', function (): void {
    expect($this->whenObject)
        ->when(
            0,
            fn ($value) => $value->trueValue->toBeFalse()
        )
        ->when(
            false,
            fn ($value) => $value->trueValue->toBeFalse()
        )
        ->foo->toEqual('foo')
        ->and(static::getCount())->toBe(1);
});

it('runs with truthy closure condition', function (): void {
    expect($this->whenObject)
        ->when(
            fn (): string => '1',
            fn ($value) => $value->trueValue->toBeTrue()
        )
        ->foo->toEqual('foo')
        ->and(static::getCount())->toBe(2);
});

it('skips with falsy closure condition', function (): void {
    expect($this->whenObject)
        ->when(
            fn (): string => '0',
            fn ($value) => $value->trueValue->toBeFalse()
        )
        ->foo->toEqual('foo')
        ->and(static::getCount())->toBe(1);
});

it('can be used in higher order tests')
    ->expect(false)
    ->when(
        fn (): true => true,
        fn ($value) => $value->toBeTrue()
    )
    ->throws(ExpectationFailedException::class, 'false is true');
