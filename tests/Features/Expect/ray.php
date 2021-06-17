<?php

test('ray calls do not fail when ray is not installed', function () {
    expect(true)->ray()->toBe(true);
});
