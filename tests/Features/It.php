<?php

it('is a test', function () {
    $this->assertArrayHasKey('key', ['key' => 'foo']);
});

it('is a higher order message test')->expect(true)->toBeTrue();
