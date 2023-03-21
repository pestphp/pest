<?php

test('global functions are loaded', function () {
    expect(helper_returns_string())->toBeString();
});
