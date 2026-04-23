<?php

it('notices', function () {
    trigger_error('user notice', E_USER_NOTICE);

    expect(true)->toBeTrue();
});
