<?php

declare(strict_types=1);

beforeEach()->skip();

test('does not run 1', function (): void {
    $this->fail('This test should not run');
});

test('does not run 2', function (): void {
    $this->fail('This test should not run');
});

test('does not run 3', function (): void {
    $this->fail('This test should not run');
});
