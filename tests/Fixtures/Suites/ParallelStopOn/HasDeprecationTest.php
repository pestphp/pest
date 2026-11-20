<?php

it('deprecates', function () {
    trigger_error('user deprecation', E_USER_DEPRECATED);

    expect(true)->toBeTrue();
});
