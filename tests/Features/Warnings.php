<?php

declare(strict_types=1);

test('warning', function (): void {
    $this->fooqwdfwqdfqw;

    expect(true)->toBeTrue();
});

test('user warning', function (): void {
    trigger_error('This is a warning description', E_USER_WARNING);

    expect(true)->toBeTrue();
});

describe('a "describe" group of tests', function (): void {
    test('user warning', function (): void {
        trigger_error('This is a warning description', E_USER_WARNING);

        expect(true)->toBeTrue();
    });
});
