<?php

it('gives access the the underlying expectException', function (): void {
    $this->expectException(InvalidArgumentException::class);

    throw new InvalidArgumentException;
});

it('catch exceptions', function (): void {
    throw new Exception('Something bad happened');
})->throws(Exception::class);

it('catch exceptions and messages', function (): void {
    throw new Exception('Something bad happened');
})->throws(Exception::class, 'Something bad happened');

it('catch exceptions, messages and code', function (): void {
    throw new Exception('Something bad happened', 1);
})->throws(Exception::class, 'Something bad happened', 1);

it('can just define the message', function (): void {
    throw new Exception('Something bad happened');
})->throws('Something bad happened');

it('can just define the code', function (): void {
    throw new Exception('Something bad happened', 1);
})->throws(1);

it('not catch exceptions if given condition is false', function (): void {
    $this->assertTrue(true);
})->throwsIf(false, Exception::class);

it('catch exceptions if given condition is true', function (): void {
    throw new Exception('Something bad happened');
})->throwsIf(fn (): true => true, Exception::class);

it('catch exceptions and messages if given condition is true', function (): void {
    throw new Exception('Something bad happened');
})->throwsIf(true, Exception::class, 'Something bad happened');

it('catch exceptions, messages and code if given condition is true', function (): void {
    throw new Exception('Something bad happened', 1);
})->throwsIf(true, Exception::class, 'Something bad happened', 1);

it('can just define the message if given condition is true', function (): void {
    throw new Exception('Something bad happened');
})->throwsIf(true, 'Something bad happened');

it('can just define the code if given condition is true', function (): void {
    throw new Exception('Something bad happened', 1);
})->throwsIf(true, 1);

it('can just define the message if given condition is 1', function (): void {
    throw new Exception('Something bad happened');
})->throwsIf(1, 'Something bad happened');

it('can just define the code if given condition is 1', function (): void {
    throw new Exception('Something bad happened', 1);
})->throwsIf(1, 1);

it('not catch exceptions if given condition is true', function (): void {
    $this->assertTrue(true);
})->throwsUnless(true, Exception::class);

it('catch exceptions if given condition is false', function (): void {
    throw new Exception('Something bad happened');
})->throwsUnless(fn (): false => false, Exception::class);

it('catch exceptions and messages if given condition is false', function (): void {
    throw new Exception('Something bad happened');
})->throwsUnless(false, Exception::class, 'Something bad happened');

it('catch exceptions, messages and code if given condition is false', function (): void {
    throw new Exception('Something bad happened', 1);
})->throwsUnless(false, Exception::class, 'Something bad happened', 1);

it('can just define the message if given condition is false', function (): void {
    throw new Exception('Something bad happened');
})->throwsUnless(false, 'Something bad happened');

it('can just define the code if given condition is false', function (): void {
    throw new Exception('Something bad happened', 1);
})->throwsUnless(false, 1);

it('can just define the message if given condition is 0', function (): void {
    throw new Exception('Something bad happened');
})->throwsUnless(0, 'Something bad happened');

it('can just define the code if given condition is 0', function (): void {
    throw new Exception('Something bad happened', 1);
})->throwsUnless(0, 1);
