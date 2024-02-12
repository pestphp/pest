<?php

use PHPUnit\Framework\ExpectationFailedException;

describe('Check whether one number is a power of another', function () {
    test('pass', function () {
        expect(8)->toBePowerOf(2)
            ->and(7)->not->toBePowerOf(2);
    });

    test('failures', function () {
        expect(7)->toBePowerOf(2);
    })->throws(ExpectationFailedException::class);

    test('failures with custom message', function () {
        expect(7)->toBePowerOf(2, 'oh no!');
    })->throws(
        ExpectationFailedException::class,
        'oh no!'
    );

    test('not failures', function () {
        expect(8)->not->toBePowerOf(2);
    })->throws(ExpectationFailedException::class);
});
