<?php

it('is a test', function () {
    expect(['key' => 'foo'])->toHaveKey('key')->key->toBeString();
});

it('is a higher order message test')->expect(true)->toBeTrue();
