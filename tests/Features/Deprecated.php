<?php

error_reporting(E_ALL);

test('deprecated', function () {
    str_contains(null, null);

    expect(true)->toBeTrue();
});

test('user deprecated', function () {
    trigger_deprecation('foo', '1.0', 'This is a deprecation description');
    @trigger_error(($package || $version ? "Since $package $version: " : '').($args ? vsprintf($message, $args) : $message), \E_USER_DEPRECATED);
    expect(true)->toBeTrue();
});
