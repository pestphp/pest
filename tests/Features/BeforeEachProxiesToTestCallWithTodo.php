<?php

beforeEach()->todo();

test('is marked as todo 1', function () {
    $this->fail('This test should not run');
});

test('is marked as todo 2', function () {
    $this->fail('This test should not run');
});

test('is marked as todo 3');

test()->shouldBeMarkedAsTodo();
