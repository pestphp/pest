<?php

test('notice', function () {
    trigger_error('This is a notice description', E_USER_NOTICE);

    expect(true)->toBeTrue();
});

describe('a "describe" group of tests', function () {
    test('notice', function () {
        trigger_error('This is a notice description', E_USER_NOTICE);

        expect(true)->toBeTrue();
    });
});
