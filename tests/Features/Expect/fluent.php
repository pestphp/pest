<?php

it('can run as fluent', function () {
    $foo = 'foO';

    expect($foo)
        ->toBe('foO')
        ->and()
        ->toContain('O')
        ->and()
        ->not
        ->toBe('Bar');
});

it('can run with parameter', function () {
    $foo = 'foO';

    expect($foo)
        ->toBe('foO')
        ->and($foo .= 'Bar')
        ->toContain('Bar');
});
