<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;

describe(
    'check the validity of an email',
    function (): void {
        test('pass', function () {
            expect('test@example.com')->toBeEmail();
        });

        test('failures', function (string $email) {
            expect($email)->toBeEmail();
        })
            ->with([
                'test',
                'test.com',
                'test@localhost',
            ])
            ->throws(ExpectationFailedException::class);

        test('failures with custom message', function () {
            expect('test@localhost')->toBeEmail('oh no!');
        })
            ->throws(
                ExpectationFailedException::class,
                'oh no!'
            );

        test('failures with default message', function () {
            expect('test@localhost')->toBeEmail();
        })
            ->throws(
                ExpectationFailedException::class,
                'Failed asserting that test@localhost is an email'
            );

        test('not failures', function () {
            expect('test@example.com')->not->toBeEmail();
        })
            ->throws(ExpectationFailedException::class);
    }
);
