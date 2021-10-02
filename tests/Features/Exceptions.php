<?php

use PHPUnit\Framework\ExpectationFailedException;

it('gives access the the underlying expectException', function () {
    $this->expectException(InvalidArgumentException::class);

    throw new InvalidArgumentException();
});

it('catch exceptions', function () {
    throw new Exception('Something bad happened');
})->throws(Exception::class);

it('catch exceptions and messages', function () {
    throw new Exception('Something bad happened');
})->throws(Exception::class, 'Something bad happened');

it('can just define the message', function () {
    throw new Exception('Something bad happened');
})->throws('Something bad happened');

it('not catch exceptions if given condition is false', function () {
    $this->assertTrue(true);
})->throwsIf(false, Exception::class);

it('catch exceptions if given condition is true', function () {
    throw new Exception('Something bad happened');
})->throwsIf(function () { return true; }, Exception::class);

it('catch exceptions and messages if given condition is true', function () {
    throw new Exception('Something bad happened');
})->throwsIf(true, Exception::class, 'Something bad happened');

it('can just define the message if given condition is true', function () {
    throw new Exception('Something bad happened');
})->throwsIf(true, 'Something bad happened');

it('can just define the message if given condition is 1', function () {
    throw new Exception('Something bad happened');
})->throwsIf(1, 'Something bad happened');

it('can handle a skipped test if it is trying to catch an exception', function () {
    expect(1)->toBe(2);
})->throws(ExpectationFailedException::class)->skip('this test should be skipped')->only();
