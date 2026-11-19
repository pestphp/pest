<?php

declare(strict_types=1);

test('reports a deprecation', function (): void {
    trigger_error('fixture deprecation', E_USER_DEPRECATED);

    expect(true)->toBeTrue();
});

test('reports a warning', function (): void {
    trigger_error('fixture warning', E_USER_WARNING);

    expect(true)->toBeTrue();
});

test('reports a notice', function (): void {
    trigger_error('fixture notice', E_USER_NOTICE);

    expect(true)->toBeTrue();
});

test('reports an incomplete test', function (): void {
    $this->markTestIncomplete('fixture incomplete');
});

test('reports a skipped test')->skip('fixture skipped');

todo('reports a todo');
