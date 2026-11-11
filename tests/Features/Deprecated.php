<?php

declare(strict_types=1);

test('deprecated', function (): void {
    str_contains('', '');

    expect(true)->toBeTrue();
});

test('user deprecated', function (): void {
    trigger_error('Since foo 1.0: This is a deprecation description', \E_USER_DEPRECATED);

    expect(true)->toBeTrue();
});
