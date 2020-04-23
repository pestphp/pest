<?php

use Pest\Support\Reflection;

it('gets file name from closure', function () {
    $fileName = Reflection::getFileNameFromClosure(function () {});

    assertEquals(__FILE__, $fileName);
});

it('gets property values', function () {
    $class = new class() {
        private $foo = 'bar';
    };

    $value = Reflection::getPropertyValue($class, 'foo');

    assertEquals('bar', $value);
});
