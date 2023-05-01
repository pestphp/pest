<?php

beforeEach()->skip();

test('does not run 1', function () {
    $this->fail('This test should not run');
});

test('does not run 2', function () {
    $this->fail('This test should not run');
});

test('does not run 3', function () {
    $this->fail('This test should not run');
});
