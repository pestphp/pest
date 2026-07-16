<?php

test('a test inside the filtered group', function () {
    expect(true)->toBeTrue();
})->group('filtered-group');
