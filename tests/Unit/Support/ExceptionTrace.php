<?php

use Pest\Support\ExceptionTrace;

it('ensures the given closures reports the correct class name', function () {
    $closure = function () {
        throw new Exception('Call to undefined method P\Tests\IntentionallyNotExisting::testBasic().');
    };

    ExceptionTrace::ensure($closure);
})->throws(
    Exception::class,
    'Call to undefined method Tests\IntentionallyNotExisting::testBasic().',
);

it('ensures the given closures reports the correct class name and suggests the [pest()] function', function () {
    $this->get();
})->throws(
    Error::class,
    'Call to undefined method Tests\Unit\Support\ExceptionTrace::get(). Did you forget to use the [pest()->extend()] function? Read more at: https://pestphp.com/docs/configuring-tests',
);
