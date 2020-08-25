<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;
use Pest\Exceptions\ShouldNotHappen;
use Pest\TestSuite;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

/**
 * @internal
 */
final class Reflection
{
    /**
     * Calls the given method with args on the given object.
     *
     * @param array<int, mixed> $args
     *
     * @return mixed
     */
    public static function call(object $object, string $method, array $args = [])
    {
        $reflectionClass = new ReflectionClass($object);

        try {
            $reflectionMethod = $reflectionClass->getMethod($method);

            $reflectionMethod->setAccessible(true);

            return $reflectionMethod->invoke($object, ...$args);
        } catch (ReflectionException $exception) {
            if (method_exists($object, '__call')) {
                return $object->__call($method, $args);
            }

            if (is_callable($method)) {
                return Closure::fromCallable($method)->bindTo(
                    TestSuite::getInstance()->test
                )(...$args);
            }

            throw $exception;
        }
    }

    /**
     * Infers the file name from the given closure.
     */
    public static function getFileNameFromClosure(Closure $closure): string
    {
        $reflectionClosure = new ReflectionFunction($closure);

        return (string) $reflectionClosure->getFileName();
    }

    /**
     * Gets the property value from of the given object.
     *
     * @return mixed
     */
    public static function getPropertyValue(object $object, string $property)
    {
        $reflectionClass = new ReflectionClass($object);

        $reflectionProperty = null;

        while ($reflectionProperty === null) {
            try {
                /* @var ReflectionProperty $reflectionProperty */
                $reflectionProperty = $reflectionClass->getProperty($property);
            } catch (ReflectionException $reflectionException) {
                $reflectionClass = $reflectionClass->getParentClass();

                if (!$reflectionClass instanceof ReflectionClass) {
                    throw new ShouldNotHappen($reflectionException);
                }
            }
        }

        if ($reflectionProperty === null) {
            throw ShouldNotHappen::fromMessage('Reflection property not found.');
        }

        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($object);
    }

    /**
     * Sets the property value of the given object.
     *
     * @param mixed $value
     */
    public static function setPropertyValue(object $object, string $property, $value): void
    {
        /** @var ReflectionClass $reflectionClass */
        $reflectionClass = new ReflectionClass($object);

        $reflectionProperty = null;

        while ($reflectionProperty === null) {
            try {
                /* @var ReflectionProperty $reflectionProperty */
                $reflectionProperty = $reflectionClass->getProperty($property);
            } catch (ReflectionException $reflectionException) {
                $reflectionClass = $reflectionClass->getParentClass();

                if (!$reflectionClass instanceof ReflectionClass) {
                    throw new ShouldNotHappen($reflectionException);
                }
            }
        }

        if ($reflectionProperty === null) {
            throw ShouldNotHappen::fromMessage('Reflection property not found.');
        }

        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($object, $value);
    }

    /**
     * Get the class name of the given parameter's type, if possible.
     *
     * @see https://github.com/laravel/framework/blob/v6.18.25/src/Illuminate/Support/Reflector.php
     */
    public static function getParameterClassName(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $name = $type->getName();

        if (($class = $parameter->getDeclaringClass()) instanceof ReflectionClass) {
            if ($name === 'self') {
                return $class->getName();
            }

            if ($name === 'parent' && ($parent = $class->getParentClass()) instanceof ReflectionClass) {
                return $parent->getName();
            }
        }

        return $name;
    }
}
