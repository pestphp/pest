<?php

use PHPUnit\Framework\ExpectationFailedException;

test('pass', function () {
    expect('printable')->toBePrintable();
    expect("not\n\r\tprintable")->not->toBePrintable();
});

test('failures', function () {
    expect(null)->toBePrintable();
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect(null)->toBePrintable('oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect('printable')->not->toBePrintable();
})->throws(ExpectationFailedException::class);
