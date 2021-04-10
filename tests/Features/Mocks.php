<?php

use Mockery\CompositeExpectation;
use Mockery\MockInterface;

interface Http
{
    public function get(): string;
}

it('can mock methods', function () {
    $mock = mock(Http::class)->expect(
        get: 'foo',
    );

    expect($mock->get())->toBe('foo');
})->skip(((float) phpversion()) < 8.0);

test('access to the mock object', function () {
    $mock = mock(Http::class);
    expect($mock->expect())->toBeInstanceOf(MockInterface::class);

    expect($mock->shouldReceive())->toBeInstanceOf(CompositeExpectation::class);
})->skip(((float) phpversion()) < 8.0);
