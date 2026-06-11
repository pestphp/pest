<?php

use PHPUnit\Framework\ExpectationFailedException;

it('dd throws ExpectationFailedException with dumped value in parallel mode', function () {
    putenv('PARATEST=1');

    try {
        expect(42)->dd();
    } catch (ExpectationFailedException $e) {
        expect($e->getMessage())->toContain('42');
    } finally {
        putenv('PARATEST');
    }
});

it('dd throws ExpectationFailedException with all dumped arguments in parallel mode', function () {
    putenv('PARATEST=1');

    try {
        expect('hello')->dd('extra');
    } catch (ExpectationFailedException $e) {
        expect($e->getMessage())
            ->toContain('hello')
            ->toContain('extra');
    } finally {
        putenv('PARATEST');
    }
});

it('dd throws ExpectationFailedException with dumped value when running under pest', function () {
    expect($_SERVER['COLLISION_PRINTER'])->toBe('DefaultPrinter');

    try {
        expect(42)->dd();
    } catch (ExpectationFailedException $e) {
        expect($e->getMessage())->toContain('42');
    }
});