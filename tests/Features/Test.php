<?php

beforeEach(function () {
    $this->dynamic = 'foo';
});

test('a test', function () {
    $this->assertArrayHasKey('key', ['key' => 'foo']);
});

test('higher order message test')->expect(true)->toBeTrue();

test('dynamic properties test', function () {
    expect($this->dynamic)->toBe('foo');
});
