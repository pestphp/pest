<?php

class MethodOverriddenTest extends PHPUnit\Framework\TestCase
{
    public $foo;

    public $bar = 'foo';

    public $baz = [
        'baz' => 'baz',
    ];

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
}

$typedOverride = function (string $foo, ? int $bar): string {
    $this->assertEquals($foo, 'foo');
    $this->assertEquals($bar, 123);

    return $foo;
};

$untypedOverride = function ($foo) {
    $this->assertEquals($foo, 'foo');

    return $foo;
};

uses(MethodOverriddenTest::class)->with([
    'assertOverride'        => function () { $this->assertTrue(true); },
    'assertTypedOverride'   => $typedOverride,
    'assertUntypedOverride' => $untypedOverride,
    'overrideableConfig'    => function (): array { return ['foo' => 'baz']; },
    'getStaticValue'        => function (): int { return 42; },
    'foo'                   => 'foo-override',
    'bar'                   => 'bar-override',
    'baz'                   => ['baz' => 'baz-override'],
]);

test('methods can be overridden', function () {
    $this->assertOverride();
});

test('typed methods can be overridden', function () {
    $this->assertTypedOverride('foo', 123);
});

test('untyped methods can be overridden', function () {
    $this->assertUntypedOverride('foo');
});

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
