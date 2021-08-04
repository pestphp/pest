<?php

use PHPUnit\Framework\ExpectationFailedException;

test('passes', function () {
    expect(false)->toBeFalsy();
    expect('')->toBeFalsy();
    expect(null)->toBeFalsy();
    expect([])->toBeFalsy();
    expect(0)->toBeFalsy();
    expect('0')->toBeFalsy();

    expect(true)->not->toBeFalsy();
    expect([1])->not->toBeFalsy();
    expect('false')->not->toBeFalsy();
    expect(1)->not->toBeFalsy();
    expect(-1)->not->toBeFalsy();
});

test('failures', function () {
    expect(1)->toBeFalsy();
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(null)->not->toBeFalsy();
})->throws(ExpectationFailedException::class);
