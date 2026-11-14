<?php

use Pest\Support\Reflection;

it('gets file name from closure', function (): void {
    $fileName = Reflection::getFileNameFromClosure(function (): void {});

    expect($fileName)->toBe(__FILE__);
});

it('gets property values', function (): void {
    $class = new class
    {
        private string $foo = 'bar';
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

it('gets properties from classes', function (): void {
    $reflectionClass = new ReflectionClass(Qwe::class);

    $properties = Reflection::getPropertiesFromReflectionClass($reflectionClass);

    $properties = array_map(fn (ReflectionProperty $property) => $property->getName(), $properties);

    expect($properties)->toBe([
        'bar',
    ]);
});

it('gets methods from classes', function (): void {
    $reflectionClass = new ReflectionClass(Qwe::class);

    $methods = Reflection::getMethodsFromReflectionClass($reflectionClass);

    $methods = array_map(fn (ReflectionMethod $method) => $method->getName(), $methods);

    expect($methods)->toBe([
        'getBar',
    ]);
});

it('calls instance methods', function (): void {
    $class = new class
    {
        private string $foo = 'bar';

        private function getFoo(): string
        {
            return $this->foo;
        }
    };

    expect(Reflection::call($class, 'getFoo'))->toBe('bar');
});

it('calls static methods without passing an object', function (): void {
    $class = new class
    {
        private static function concat(string $a, string $b): string
        {
            return $a.$b;
        }
    };

    expect(Reflection::call($class, 'concat', ['foo', 'bar']))->toBe('foobar');
});
