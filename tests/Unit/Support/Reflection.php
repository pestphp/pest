<?php

use Pest\Support\Reflection;

it('gets file name from closure', function () {
    $fileName = Reflection::getFileNameFromClosure(function () {});

    expect($fileName)->toBe(__FILE__);
});

it('gets property values', function () {
    $class = new class
    {
        private $foo = 'bar';
    };

    $value = Reflection::getPropertyValue($class, 'foo');

    expect($value)->toBe('bar');
});

class Asd
{
    protected $foo = 'bar';

    public function getFoo()
    {
        return $this->foo;
    }
}

trait Zxc
{
    protected $baz = 'qux';

    public function getBaz()
    {
        return $this->baz;
    }
}

class Qwe extends Asd
{
    use Zxc;

    protected $bar = 'baz';

    public function getBar()
    {
        return $this->bar;
    }
}

it('gets properties from classes', function () {
    $reflectionClass = new ReflectionClass(Qwe::class);

    $properties = Reflection::getPropertiesFromReflectionClass($reflectionClass);

    $properties = array_map(fn ($property) => $property->getName(), $properties);

    expect($properties)->toBe([
        'bar',
    ]);
});

it('gets methods from classes', function () {
    $reflectionClass = new ReflectionClass(Qwe::class);

    $methods = Reflection::getMethodsFromReflectionClass($reflectionClass);

    $methods = array_map(fn ($method) => $method->getName(), $methods);

    expect($methods)->toBe([
        'getBar',
    ]);
});
