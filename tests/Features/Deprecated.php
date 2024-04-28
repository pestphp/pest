<?php

test('deprecated', function () {
    str_contains(null, null);

    expect(true)->toBeTrue();
});

test('user deprecated', function () {
    trigger_error('Since foo 1.0: This is a deprecation description', \E_USER_DEPRECATED);

    expect(true)->toBeTrue();
});
