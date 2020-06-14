<?php

use Pest\Exceptions\ShouldNotHappen;
use Pest\Support\Container;
use Pest\TestSuite;

uses()->group('container');

beforeEach(function () {
    $this->container = new Container();
});

it('exists')
    ->assertTrue(class_exists(Container::class));

it('gets an instance', function () {
    $this->container->add(Container::class, $this->container);
    assertSame($this->container, $this->container->get(Container::class));
});

test('autowire', function () {
    assertInstanceOf(Container::class, $this->container->get(Container::class));
});

it('creates an instance and resolves parameters', function () {
    $this->container->add(Container::class, $this->container);
    $instance = $this->container->get(ClassWithDependency::class);

    assertInstanceOf(ClassWithDependency::class, $instance);
});

it('creates an instance and resolves also sub parameters', function () {
    $this->container->add(Container::class, $this->container);
    $instance = $this->container->get(ClassWithSubDependency::class);

    assertInstanceOf(ClassWithSubDependency::class, $instance);
});

it('can resolve builtin value types', function () {
    $this->container->add('rootPath', getcwd());

    $instance = $this->container->get(TestSuite::class);
    assertInstanceOf(TestSuite::class, $instance);
});

it('cannot resolve a parameter without type', function () {
    $this->container->get(ClassWithoutTypeParameter::class);
})->throws(ShouldNotHappen::class);

class ClassWithDependency
{
    public function __construct(Container $container)
    {
    }
}

class ClassWithSubDependency
{
    public function __construct(ClassWithDependency $param)
    {
    }
}

class ClassWithoutTypeParameter
{
    public function __construct($param)
    {
    }
}
