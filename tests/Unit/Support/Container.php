<?php

use Pest\Exceptions\ShouldNotHappen;
use Pest\Support\Container;
use Pest\TestSuite;

pest()->group('container');

beforeEach(function () {
    $this->container = new Container;
});

it('exists')
    ->assertTrue(class_exists(Container::class));

it('gets an instance', function () {
    $this->container->add(Container::class, $this->container);
    expect($this->container->get(Container::class))->toBe($this->container);
});

test('autowire', function () {
    expect($this->container->get(Container::class))->toBeInstanceOf(Container::class);
});

it('creates an instance and resolves parameters', function () {
    $this->container->add(Container::class, $this->container);
    $instance = $this->container->get(ClassWithDependency::class);

    expect($instance)->toBeInstanceOf(ClassWithDependency::class);
});

it('creates an instance and resolves also sub parameters', function () {
    $this->container->add(Container::class, $this->container);
    $instance = $this->container->get(ClassWithSubDependency::class);

    expect($instance)->toBeInstanceOf(ClassWithSubDependency::class);
});

it('can resolve builtin value types', function () {
    $this->container->add('rootPath', getcwd());
    $this->container->add('testPath', 'tests');

    $instance = $this->container->get(TestSuite::class);
    expect($instance)->toBeInstanceOf(TestSuite::class);
});

it('cannot resolve a parameter without type', function () {
    $this->container->get(ClassWithoutTypeParameter::class);
})->throws(ShouldNotHappen::class);

class ClassWithDependency
{
    public function __construct(Container $container) {}
}

class ClassWithSubDependency
{
    public function __construct(ClassWithDependency $param) {}
}

class ClassWithoutTypeParameter
{
    public function __construct($param) {}
}
