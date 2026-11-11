<?php

declare(strict_types=1);

test('global functions are loaded', function (): void {
    expect(helper_returns_string())->toBeString();
});
