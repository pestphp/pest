<?php

declare(strict_types=1);

test('a test', function (): void {
    expect(['key' => 'foo'])->toHaveKey('key');
});

test('higher order message test')->expect(true)->toBeTrue();
