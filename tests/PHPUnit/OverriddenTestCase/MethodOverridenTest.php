<?php

use Tests\PHPUnit\OverriddenTestCase\DummyFoo;

class MethodOverriddenTest extends PHPUnit\Framework\TestCase
{
    public $foo;

    public $bar = 'foo';

    public $baz = [
        'baz' => 'baz',
    ];

    public $propertyThatIsAlsoAMethod = 'property';

    public function assertOverride()
    {
        $this->assertTrue(false);
    }

    public function assertTypedOverride(string $foo, ? int $bar = null): string
    {
        $this->assertTrue(false);
        $this->assertEquals($foo, 'bar');
        $this->assertEquals($bar, 456);

        return $foo;
    }

    public function assertUntypedOverride($foo, $bar = 123, $baz = 'bap')
    {
        $this->assertTrue(false);
        $this->assertEquals($foo, 'bar');
        $this->assertEquals($baz, 'baz');
        $this->assertEquals($bar, 456);

        return $foo;
    }

    protected function overrideableConfig($config = ['foo' => 'bar', 'baz', 123, 'a' => ['b' => ['c']]]): array
    {
        return $config;
    }

    public static function getStaticValue(): int
    {
        return 0;
    }

    public function propertyThatIsAlsoAMethod(): string
    {
        return 'method';
    }

    public function methodThatReturnsAClosure(): Closure
    {
        return function (): string { return 'foo'; };
    }

    public function methodThatReturnsAClass(): DummyFoo
    {
        return new DummyFoo('foo');
    }
}

uses(MethodOverriddenTest::class)

    ->extends([
        'assertOverride'        => function () { $this->assertTrue(true); },
        'overrideableConfig'    => function (): array { return ['foo' => 'baz']; },
        'getStaticValue'        => function (): int { return 42; },
    ])

    ->extends('assertUntypedOverride', function ($foo) {
        $this->assertEquals($foo, 'foo');

        return $foo;
    })

    ->extends('assertTypedOverride', function (string $foo, ? int $bar): string {
        $this->assertEquals($foo, 'foo');
        $this->assertEquals($bar, 123);

        return $foo;
    })

    ->with('foo', 'foo-override')

    ->with([
        'bar'   => 'bar-override',
        'baz'   => ['baz' => 'baz-override'],
    ])

    ->extends('propertyThatIsAlsoAMethod', function (): string {
        return 'method-override';
    })

    ->with('propertyThatIsAlsoAMethod', 'property-override')

    ->extends('methodThatReturnsAClosure', function () {
        return function () {
            return 'bar';
        };
    })

    ->extends('methodThatReturnsAClass', function () {
        return new DummyFoo('bar');
    });

test('methods can be overridden')->assertOverride();

test('typed methods can be overridden')->assertTypedOverride('foo', 123);

test('untyped methods can be overridden')->assertUntypedOverride('foo');

test('protected methods can be overridden', function () {
    $this->assertEquals('baz', $this->overrideableConfig()['foo']);
});

test('static methods can be overridden', function () {
    $this->assertEquals(42, self::getStaticValue());
});

test('properties can be overridden', function () {
    $this->assertEquals('foo-override', $this->foo);
    $this->assertEquals('bar-override', $this->bar);
    $this->assertEquals(['baz' => 'baz-override'], $this->baz);
});

test('overriding a method that has the same name as a property works', function () {
    $this->assertEquals('property-override', $this->propertyThatIsAlsoAMethod);
    $this->assertEquals('method-override', $this->propertyThatIsAlsoAMethod());
});

test('that a closure can return a closure without issues', function () {
    $this->assertEquals('bar', $this->methodThatReturnsAClosure()());
});

test('that a method can return a custom class without issues', function () {
    $this->assertEquals('bar', $this->methodThatReturnsAClass()->value);
});
