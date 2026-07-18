<?php

beforeEach()->todo();

test('is marked as todo 1', function (): void {
    $this->fail('This test should not run');
});

test('is marked as todo 2', function (): void {
    $this->fail('This test should not run');
});

test('is marked as todo 3');

test()->shouldBeMarkedAsTodo();
