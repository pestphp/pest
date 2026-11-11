<?php

use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
    $this->matched = null;
});

it('pass', function (): void {
    expect('baz')
        ->match('foo', [
            'bar' => function ($value) {
                $this->matched = 'bar';

                return $value->toEqual('bar');
            },
            'foo' => function ($value) {
                $this->matched = 'baz';

                return $value->toEqual('baz');
            },
        ]
        )
        ->toEqual($this->matched)
        ->and(static::getCount())->toBe(2);
});

it('failures', function (): void {
    expect(true)
        ->match('foo', [
            'bar' => fn ($value) => $value->toBeTrue(),
            'foo' => fn ($value) => $value->toBeFalse(),
        ]
        );
})->throws(ExpectationFailedException::class, 'true is false');

it('runs with truthy', function (): void {
    expect('foo')
        ->match(1, [
            'bar' => function ($value) {
                $this->matched = 'bar';

                return $value->toEqual('bar');
            },
            true => function ($value) {
                $this->matched = 'foo';

                return $value->toEqual('foo');
            },
        ]
        )
        ->toEqual($this->matched)
        ->and(static::getCount())->toBe(2);
});

it('runs with falsy', function (): void {
    expect('foo')
        ->match(false, [
            'bar' => function ($value) {
                $this->matched = 'bar';

                return $value->toEqual('bar');
            },
            false => function ($value) {
                $this->matched = 'foo';

                return $value->toEqual('foo');
            },
        ]
        )
        ->toEqual($this->matched)
        ->and(static::getCount())->toBe(2);
});

it('runs with truthy closure condition', function (): void {
    expect('foo')
        ->match(
            fn (): string => '1', [
                'bar' => function ($value) {
                    $this->matched = 'bar';

                    return $value->toEqual('bar');
                },
                true => function ($value) {
                    $this->matched = 'foo';

                    return $value->toEqual('foo');
                },
            ]
        )
        ->toEqual($this->matched)
        ->and(static::getCount())->toBe(2);
});

it('runs with falsy closure condition', function (): void {
    expect('foo')
        ->match(
            fn (): string => '0', [
                'bar' => function ($value) {
                    $this->matched = 'bar';

                    return $value->toEqual('bar');
                },
                false => function ($value) {
                    $this->matched = 'foo';

                    return $value->toEqual('foo');
                },
            ]
        )
        ->toEqual($this->matched)
        ->and(static::getCount())->toBe(2);
});

it('can be passed non-callable values', function (): void {
    expect('foo')
        ->match('pest', [
            'bar' => 'foo',
            'pest' => 'baz',
        ]
        );
})->throws(ExpectationFailedException::class, 'two strings are equal');

it('fails with unhandled match', function (): void {
    expect('foo')->match('bar', []);
})->throws(ExpectationFailedException::class, 'Unhandled match value.');

it('can be used in higher order tests')
    ->expect(true)
    ->match(
        fn (): true => true, [
            false => fn ($value) => $value->toBeFalse(),
            true => fn ($value) => $value->toBeTrue(),
        ]
    );
