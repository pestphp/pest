<?php

declare(strict_types=1);

test('a test', function (): void {
    $this->assertArrayHasKey('key', ['key' => 'foo']);
});

test('higher order message test')->expect(true)->toBeTrue();
