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
