<?php

it('has combined issues', function (): void {
    trigger_error('combined warning', E_USER_WARNING);

    expect(false)->toBeTrue();
});

it('has repeated warnings', function (): void {
    trigger_error('first warning', E_USER_WARNING);
    trigger_error('second warning', E_USER_WARNING);

    expect(true)->toBeTrue();
});

it('has repeated notifications', function (): void {
    trigger_error('first notice', E_USER_NOTICE);
    trigger_error('second notice', E_USER_NOTICE);

    expect(true)->toBeTrue();
});
