<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;
use Pest\Exceptions\ShouldNotHappen;
use Pest\TestSuite;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
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

    /**
     * @param class-string $class
     */
    public static function getReturnType(string $class, string $method): string
    {
        /**
         * @var ReflectionNamedType | null
         */
        $returnType = self::getReflectionMethod($class, $method)->getReturnType();

        if ($returnType === null) {
            return '';
        }

        $typehint = self::getTypeHint($returnType);

        return ': ' . (class_exists($typehint) ? ' \\' . $typehint : $typehint);
    }

    /**
     * @param class-string $class
     */
    public static function getMethodSignature(string $class, string $method): string
    {
        return implode(', ', array_map(function (ReflectionParameter $parameter): string {
            /**
             * @var ReflectionNamedType | null
             */
            $reflectionType = $parameter->getType();

            $typeHint = $reflectionType === null ? '' : self::getTypeHint($reflectionType);

            return sprintf('%s $%s %s', $typeHint, $parameter->getName(), self::getDefaultParameterValue($parameter));
        }, self::getReflectionMethod($class, $method)->getParameters()));
    }

    /**
     * @param class-string $class
     */
    public static function isMethodStatic(string $class, string $method): bool
    {
        return self::getReflectionMethod($class, $method)->isStatic();
    }

    /**
     * @param class-string $class
     */
    public static function isPropertyStatic(string $class, string $property): bool
    {
        return self::getReflectionProperty($class, $property)->isStatic();
    }

    /**
     * @param mixed $value
     */
    public static function encodeValue($value): string
    {
        $encoder = (array_values(array_filter(
            [
                'is_string' => function (string $value): string { return "\"$value\""; },
                'is_array'  => function (array $value): string { return static::parseArrayValue($value); },
                'is_null'   => function ($value): string { return 'NULL'; },
            ],
            function ($predicate) use ($value): bool {
                return (bool) $predicate($value);
            }, ARRAY_FILTER_USE_KEY))[0] ?? function ($value): string { return (string) $value; });

        return $encoder($value);
    }

    /**
     * @param class-string $class
     */
    private static function getReflectionMethod(string $class, string $method): ReflectionMethod
    {
        return (new ReflectionClass($class))->getMethod($method);
    }

    /**
     * @param class-string $class
     */
    private static function getReflectionProperty(string $class, string $property): ReflectionProperty
    {
        return new ReflectionProperty($class, $property);
    }

    private static function getTypeHint(ReflectionNamedType $reflectionType): string
    {
        return ($reflectionType->allowsNull() ? '? ' : '') . $reflectionType->getName();
    }

    private static function getDefaultParameterValue(ReflectionParameter $parameter): string
    {
        return $parameter->isOptional() ? ('= ' . self::encodeValue($parameter->getDefaultValue())) : '';
    }

    /**
     * @param array<mixed> $value
     */
    private static function parseArrayValue(array $value): string
    {
        return sprintf('[%s]', implode(',', array_map(function ($key) use ($value): string {
            return self::encodeValue($key) . ' => ' . self::encodeValue($value[$key]);
        }, array_keys($value))));
    }
}
