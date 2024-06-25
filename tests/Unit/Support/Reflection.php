<?php

use Pest\Support\Reflection;

it('gets file name from closure', function () {
    $fileName = Reflection::getFileNameFromClosure(function () {});

    expect($fileName)->toBe(__FILE__);
});

it('gets property values', function () {
    $class = new class()
    {
        private $foo = 'bar';
    };

    $value = Reflection::getPropertyValue($class, 'foo');

    expect($value)->toBe('bar');
});
