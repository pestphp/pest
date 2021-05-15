<?php

use Mockery\CompositeExpectation;
use Mockery\MockInterface;

interface Http
{
    public function get(): string;
}

it('can mock methods', function () {
    $mock = mock(Http::class)->expect(
        get: fn () => 'foo',
    );

    expect($mock->get())->toBe('foo');
})->skip(((float) phpversion()) < 8.0);

it('allows access to the underlying mockery mock', function () {
    $mock = mock(Http::class);

    expect($mock->expect())->toBeInstanceOf(MockInterface::class);
    expect($mock->shouldReceive())->toBeInstanceOf(CompositeExpectation::class);
})->skip(((float) phpversion()) < 8.0);
