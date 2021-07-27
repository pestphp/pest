<?php

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
})->throw_if(false, Exception::class);

it('catch exceptions if given condition is true', function () {
    throw new Exception('Something bad happened');
})->throw_if(function () { return true; }, Exception::class);

it('catch exceptions and messages if given condition is true', function () {
    throw new Exception('Something bad happened');
})->throw_if(true, Exception::class, 'Something bad happened');

it('can just define the message if given condition is true', function () {
    throw new Exception('Something bad happened');
})->throw_if(true, 'Something bad happened');

it('can just define the message if given condition is 1', function () {
    throw new Exception('Something bad happened');
})->throw_if(1, 'Something bad happened');
