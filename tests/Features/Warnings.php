<?php

test('warning', function () {
    $this->fooqwdfwqdfqw;

    expect(true)->toBeTrue();
});

test('user warning', function () {
    trigger_error('This is a warning description', E_USER_WARNING);

    expect(true)->toBeTrue();
});
