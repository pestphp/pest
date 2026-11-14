<?php

declare(strict_types=1);

afterAll(function (): void {
    file_put_contents((string) getenv('PEST_AFTER_ALL_MARKER'), 'afterAll'.PHP_EOL, FILE_APPEND);
});

it('runs the first test', function (): void {
    expect(true)->toBeTrue();
});

it('runs the second test', function (): void {
    expect(true)->toBeTrue();
})->repeat(2);
