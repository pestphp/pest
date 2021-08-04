<?php

use PHPUnit\Framework\ExpectationFailedException;

test('passes', function () {
    expect(true)->toBeTruthy();
    expect([1])->toBeTruthy();
    expect('false')->toBeTruthy();
    expect(1)->toBeTruthy();
    expect(-1)->toBeTruthy();

    expect(false)->not->toBeTruthy();
    expect('')->not->toBeTruthy();
    expect(null)->not->toBeTruthy();
    expect([])->not->toBeTruthy();
    expect(0)->not->toBeTruthy();
    expect('0')->not->toBeTruthy();
});

test('failures', function () {
    expect(null)->toBeTruthy();
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(1)->not->toBeTruthy();
})->throws(ExpectationFailedException::class);
