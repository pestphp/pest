<?php

it('warns', function () {
    trigger_error('user warning', E_USER_WARNING);

    expect(true)->toBeTrue();
});
