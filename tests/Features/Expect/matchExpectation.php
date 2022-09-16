<?php

use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function () {
    $this->matched = null;
});

it('pass', function () {
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
        ->toEqual($this->matched);

    expect(static::getCount())->toBe(2);
});

it('failures', function () {
    expect(true)
        ->match('foo', [
            'bar' => function ($value) {
                return $value->toBeTrue();
            },
            'foo' => function ($value) {
                return $value->toBeFalse();
            },
        ]
        );
})->throws(ExpectationFailedException::class, 'true is false');

it('runs with truthy', function () {
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
        ->toEqual($this->matched);

    expect(static::getCount())->toBe(2);
});

it('runs with falsy', function () {
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
        ->toEqual($this->matched);

    expect(static::getCount())->toBe(2);
});

it('runs with truthy closure condition', function () {
    expect('foo')
        ->match(
            function () {
                return '1';
            }, [
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
        ->toEqual($this->matched);

    expect(static::getCount())->toBe(2);
});

it('runs with falsy closure condition', function () {
    expect('foo')
        ->match(
            function () {
                return '0';
            }, [
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
        ->toEqual($this->matched);

    expect(static::getCount())->toBe(2);
});

it('can be passed non-callable values', function () {
    expect('foo')
        ->match('pest', [
            'bar' => 'foo',
            'pest' => 'baz',
        ]
        );
})->throws(ExpectationFailedException::class, 'two strings are equal');

it('fails with unhandled match', function () {
    expect('foo')->match('bar', []);
})->throws(ExpectationFailedException::class, 'Unhandled match value.');

it('can be used in higher order tests')
    ->expect(true)
    ->match(
        function () {
            return true;
        }, [
            false => function ($value) {
                return $value->toBeFalse();
            },
            true => function ($value) {
                return $value->toBeTrue();
            },
        ]
    );
