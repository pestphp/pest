<?php

it('is a test', function (): void {
    expect(['key' => 'foo'])->toHaveKey('key')->key->toBeString();
});

it('is a higher order message test')->expect(true)->toBeTrue();

describe('a "describe" group of tests', function (): void {
    it('is a test', function (): void {
        expect(['key' => 'foo'])->toHaveKey('key')->key->toBeString();
    });

    it('is a higher order message test')->expect(true)->toBeTrue();
});
