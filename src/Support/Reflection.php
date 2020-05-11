<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;
use Pest\Exceptions\ShouldNotHappen;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
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

        $reflectionMethod = $reflectionClass->getMethod($method);

        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invoke($object, ...$args);
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
}
