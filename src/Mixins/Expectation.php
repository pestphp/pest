<?php

declare(strict_types=1);

namespace Pest\Mixins;

use BadMethodCallException;
use Closure;
use Error;
use InvalidArgumentException;
use Pest\Exceptions\InvalidExpectationValue;
use Pest\Support\Arr;
use Pest\Support\NullClosure;
use Pest\Support\NullValue;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\ExpectationFailedException;
use ReflectionFunction;
use ReflectionNamedType;
use SebastianBergmann\Exporter\Exporter;
use Throwable;

/**
 * @internal
 *
 * @template TValue
 *
 * @mixin \Pest\Expectation<TValue>
 */
final class Expectation
{
    /**
     * The exporter instance, if any.
     */
    private Exporter|null $exporter = null;

    /**
     * Creates a new expectation.
     *
     * @param  TValue  $value
     */
    public function __construct(
        public mixed $value
    ) {
        // ..
    }

    /**
     * Asserts that two variables have the same type and
     * value. Used on objects, it asserts that two
     * variables reference the same object.
     *
     * @return self<TValue>
     */
    public function toBe(mixed $expected, string $failureMessage = ''): self
    {
        Assert::assertSame($expected, $this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is empty.
     *
     * @return self<TValue>
     */
    public function toBeEmpty(string $failureMessage = ''): self
    {
        Assert::assertEmpty($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is true.
     *
     * @return self<TValue>
     */
    public function toBeTrue(string $failureMessage = ''): self
    {
        Assert::assertTrue($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is truthy.
     *
     * @return self<TValue>
     */
    public function toBeTruthy(string $failureMessage = ''): self
    {
        Assert::assertTrue((bool) $this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is false.
     *
     * @return self<TValue>
     */
    public function toBeFalse(string $failureMessage = ''): self
    {
        Assert::assertFalse($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is falsy.
     *
     * @return self<TValue>
     */
    public function toBeFalsy(string $failureMessage = ''): self
    {
        Assert::assertFalse((bool) $this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is greater than $expected.
     *
     * @return self<TValue>
     */
    public function toBeGreaterThan(int|float $expected, string $failureMessage = ''): self
    {
        Assert::assertGreaterThan($expected, $this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is greater than or equal to $expected.
     *
     * @return self<TValue>
     */
    public function toBeGreaterThanOrEqual(int|float $expected, string $failureMessage = ''): self
    {
        Assert::assertGreaterThanOrEqual($expected, $this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is less than or equal to $expected.
     *
     * @return self<TValue>
     */
    public function toBeLessThan(int|float $expected, string $failureMessage = ''): self
    {
        Assert::assertLessThan($expected, $this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is less than $expected.
     *
     * @return self<TValue>
     */
    public function toBeLessThanOrEqual(int|float $expected, string $failureMessage = ''): self
    {
        Assert::assertLessThanOrEqual($expected, $this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that $needle is an element of the value.
     *
     * @return self<TValue>
     */
    public function toContain(mixed ...$needles): self
    {
        foreach ($needles as $needle) {
            if (is_string($this->value)) {
                // @phpstan-ignore-next-line
                Assert::assertStringContainsString((string) $needle, $this->value);
            } else {
                if (! is_iterable($this->value)) {
                    InvalidExpectationValue::expected('iterable');
                }
                Assert::assertContains($needle, $this->value);
            }
        }

        return $this;
    }

    /**
     * Asserts that the value starts with $expected.
     *
     * @param  non-empty-string  $expected
     * @return self<TValue>
     */
    public function toStartWith(string $expected, string $failureMessage = ''): self
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertStringStartsWith($expected, $this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value ends with $expected.
     *
     * @param  non-empty-string  $expected
     * @return self<TValue>
     */
    public function toEndWith(string $expected, string $failureMessage = ''): self
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertStringEndsWith($expected, $this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that $number matches value's Length.
     *
     * @return self<TValue>
     */
    public function toHaveLength(int $number, string $failureMessage = ''): self
    {
        if (is_string($this->value)) {
            Assert::assertEquals($number, mb_strlen($this->value), $failureMessage);

            return $this;
        }

        if (is_iterable($this->value)) {
            return $this->toHaveCount($number, $failureMessage);
        }

        if (is_object($this->value)) {
            $array = method_exists($this->value, 'toArray') ? $this->value->toArray() : (array) $this->value;

            Assert::assertCount($number, $array, $failureMessage);

            return $this;
        }

        throw new BadMethodCallException('Expectation value length is not countable.');
    }

    /**
     * Asserts that $count matches the number of elements of the value.
     *
     * @return self<TValue>
     */
    public function toHaveCount(int $count, string $failureMessage = ''): self
    {
        if (! is_countable($this->value) && ! is_iterable($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertCount($count, $this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value contains the property $name.
     *
     * @return self<TValue>
     */
    public function toHaveProperty(string $name, mixed $value = new NullValue(), string $failureMessage = ''): self
    {
        $this->toBeObject();

        // @phpstan-ignore-next-line
        Assert::assertTrue(property_exists($this->value, $name), $failureMessage);

        if (! $value instanceof NullValue) {
            /* @phpstan-ignore-next-line */
            Assert::assertEquals($value, $this->value->{$name}, $failureMessage);
        }

        return $this;
    }

    /**
     * Asserts that the value contains the provided properties $names.
     *
     * @param  iterable<array-key, string>  $names
     * @return self<TValue>
     */
    public function toHaveProperties(iterable $names, string $failureMessage = ''): self
    {
        foreach ($names as $name) {
            $this->toHaveProperty($name, failureMessage: $failureMessage);
        }

        return $this;
    }

    /**
     * Asserts that two variables have the same value.
     *
     * @return self<TValue>
     */
    public function toEqual(mixed $expected, string $failureMessage = ''): self
    {
        Assert::assertEquals($expected, $this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that two variables have the same value.
     * The contents of $expected and the $this->value are
     * canonicalized before they are compared. For instance, when the two
     * variables $expected and $this->value are arrays, then these arrays
     * are sorted before they are compared. When $expected and $this->value
     * are objects, each object is converted to an array containing all
     * private, protected and public attributes.
     *
     * @return self<TValue>
     */
    public function toEqualCanonicalizing(mixed $expected, string $failureMessage = ''): self
    {
        Assert::assertEqualsCanonicalizing($expected, $this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the absolute difference between the value and $expected
     * is lower than $delta.
     *
     * @return self<TValue>
     */
    public function toEqualWithDelta(mixed $expected, float $delta, string $failureMessage = ''): self
    {
        Assert::assertEqualsWithDelta($expected, $this->value, $delta, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is one of the given values.
     *
     * @param  iterable<int|string, mixed>  $values
     * @return self<TValue>
     */
    public function toBeIn(iterable $values, string $failureMessage = ''): self
    {
        Assert::assertContains($this->value, $values, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is infinite.
     *
     * @return self<TValue>
     */
    public function toBeInfinite(string $failureMessage = ''): self
    {
        Assert::assertInfinite($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is an instance of $class.
     *
     * @param  class-string  $class
     * @return self<TValue>
     */
    public function toBeInstanceOf(string $class, string $failureMessage = ''): self
    {
        Assert::assertInstanceOf($class, $this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is an array.
     *
     * @return self<TValue>
     */
    public function toBeArray(string $failureMessage = ''): self
    {
        Assert::assertIsArray($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is of type bool.
     *
     * @return self<TValue>
     */
    public function toBeBool(string $failureMessage = ''): self
    {
        Assert::assertIsBool($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is of type callable.
     *
     * @return self<TValue>
     */
    public function toBeCallable(string $failureMessage = ''): self
    {
        Assert::assertIsCallable($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is of type float.
     *
     * @return self<TValue>
     */
    public function toBeFloat(string $failureMessage = ''): self
    {
        Assert::assertIsFloat($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is of type int.
     *
     * @return self<TValue>
     */
    public function toBeInt(string $failureMessage = ''): self
    {
        Assert::assertIsInt($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is of type iterable.
     *
     * @return self<TValue>
     */
    public function toBeIterable(string $failureMessage = ''): self
    {
        Assert::assertIsIterable($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is of type numeric.
     *
     * @return self<TValue>
     */
    public function toBeNumeric(string $failureMessage = ''): self
    {
        Assert::assertIsNumeric($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is of type object.
     *
     * @return self<TValue>
     */
    public function toBeObject(string $failureMessage = ''): self
    {
        Assert::assertIsObject($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is of type resource.
     *
     * @return self<TValue>
     */
    public function toBeResource(string $failureMessage = ''): self
    {
        Assert::assertIsResource($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is of type scalar.
     *
     * @return self<TValue>
     */
    public function toBeScalar(string $failureMessage = ''): self
    {
        Assert::assertIsScalar($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is of type string.
     *
     * @return self<TValue>
     */
    public function toBeString(string $failureMessage = ''): self
    {
        Assert::assertIsString($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is a JSON string.
     *
     * @return self<TValue>
     */
    public function toBeJson(string $failureMessage = ''): self
    {
        Assert::assertIsString($this->value, $failureMessage);

        // @phpstan-ignore-next-line
        Assert::assertJson($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is NAN.
     *
     * @return self<TValue>
     */
    public function toBeNan(string $failureMessage = ''): self
    {
        Assert::assertNan($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is null.
     *
     * @return self<TValue>
     */
    public function toBeNull(string $failureMessage = ''): self
    {
        Assert::assertNull($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value array has the provided $key.
     *
     * @return self<TValue>
     */
    public function toHaveKey(string|int $key, mixed $value = new NullValue(), string $failureMessage = ''): self
    {
        if (is_object($this->value) && method_exists($this->value, 'toArray')) {
            $array = $this->value->toArray();
        } else {
            $array = (array) $this->value;
        }

        try {
            Assert::assertTrue(Arr::has($array, $key));

            /* @phpstan-ignore-next-line */
        } catch (ExpectationFailedException $exception) {
            if ($failureMessage === '') {
                $failureMessage = "Failed asserting that an array has the key '$key'";
            }

            throw new ExpectationFailedException($failureMessage, $exception->getComparisonFailure());
        }

        if (! $value instanceof NullValue) {
            Assert::assertEquals($value, Arr::get($array, $key), $failureMessage);
        }

        return $this;
    }

    /**
     * Asserts that the value array has the provided $keys.
     *
     * @param  array<int, int|string|array<array-key, mixed>>  $keys
     * @return self<TValue>
     */
    public function toHaveKeys(array $keys, string $failureMessage = ''): self
    {
        foreach ($keys as $k => $key) {
            if (is_array($key)) {
                $this->toHaveKeys(array_keys(Arr::dot($key, $k.'.')), $failureMessage);
            } else {
                $this->toHaveKey($key, failureMessage: $failureMessage);
            }
        }

        return $this;
    }

    /**
     * Asserts that the value is a directory.
     *
     * @return self<TValue>
     */
    public function toBeDirectory(string $failureMessage = ''): self
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertDirectoryExists($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is a directory and is readable.
     *
     * @return self<TValue>
     */
    public function toBeReadableDirectory(string $failureMessage = ''): self
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertDirectoryIsReadable($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is a directory and is writable.
     *
     * @return self<TValue>
     */
    public function toBeWritableDirectory(string $failureMessage = ''): self
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertDirectoryIsWritable($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is a file.
     *
     * @return self<TValue>
     */
    public function toBeFile(string $failureMessage = ''): self
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertFileExists($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is a file and is readable.
     *
     * @return self<TValue>
     */
    public function toBeReadableFile(string $failureMessage = ''): self
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertFileIsReadable($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value is a file and is writable.
     *
     * @return self<TValue>
     */
    public function toBeWritableFile(string $failureMessage = ''): self
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }
        Assert::assertFileIsWritable($this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value array matches the given array subset.
     *
     * @param  iterable<int|string, mixed>  $array
     * @return self<TValue>
     */
    public function toMatchArray(iterable $array, string $failureMessage = ''): self
    {
        if (is_object($this->value) && method_exists($this->value, 'toArray')) {
            $valueAsArray = $this->value->toArray();
        } else {
            $valueAsArray = (array) $this->value;
        }

        foreach ($array as $key => $value) {
            Assert::assertArrayHasKey($key, $valueAsArray, $failureMessage);

            if ($failureMessage === '') {
                $failureMessage = sprintf(
                    'Failed asserting that an array has a key %s with the value %s.',
                    $this->export($key),
                    $this->export($valueAsArray[$key]),
                );
            }

            Assert::assertEquals($value, $valueAsArray[$key], $failureMessage);
        }

        return $this;
    }

    /**
     * Asserts that the value object matches a subset
     * of the properties of an given object.
     *
     * @param  iterable<string, mixed>  $object
     * @return self<TValue>
     */
    public function toMatchObject(iterable $object, string $failureMessage = ''): self
    {
        foreach ((array) $object as $property => $value) {
            if (! is_object($this->value) && ! is_string($this->value)) {
                InvalidExpectationValue::expected('object|string');
            }

            Assert::assertTrue(property_exists($this->value, $property), $failureMessage);

            /* @phpstan-ignore-next-line */
            $propertyValue = $this->value->{$property};

            if ($failureMessage === '') {
                $failureMessage = sprintf(
                    'Failed asserting that an object has a property %s with the value %s.',
                    $this->export($property),
                    $this->export($propertyValue),
                );
            }

            Assert::assertEquals($value, $propertyValue, $failureMessage);
        }

        return $this;
    }

    /**
     * Asserts that the value matches a regular expression.
     *
     * @return self<TValue>
     */
    public function toMatch(string $expression, string $failureMessage = ''): self
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }
        Assert::assertMatchesRegularExpression($expression, $this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that the value matches a constraint.
     *
     * @return self<TValue>
     */
    public function toMatchConstraint(Constraint $constraint, string $failureMessage = ''): self
    {
        Assert::assertThat($this->value, $constraint, $failureMessage);

        return $this;
    }

    /**
     * @param  class-string  $class
     * @return self<TValue>
     */
    public function toContainOnlyInstancesOf(string $class, string $failureMessage = ''): self
    {
        if (! is_iterable($this->value)) {
            InvalidExpectationValue::expected('iterable');
        }

        Assert::assertContainsOnlyInstancesOf($class, $this->value, $failureMessage);

        return $this;
    }

    /**
     * Asserts that executing value throws an exception.
     *
     * @param (Closure(Throwable): mixed)|string $exception
     * @return self<TValue>
     */
    public function toThrow(callable|string $exception, string $exceptionMessage = null, string $failureMessage = ''): self
    {
        $callback = NullClosure::create();

        if ($exception instanceof Closure) {
            $callback = $exception;
            $parameters = (new ReflectionFunction($exception))->getParameters();

            if (1 !== count($parameters)) {
                throw new InvalidArgumentException('The given closure must have a single parameter type-hinted as the class string.');
            }

            if (! ($type = $parameters[0]->getType()) instanceof ReflectionNamedType) {
                throw new InvalidArgumentException('The given closure\'s parameter must be type-hinted as the class string.');
            }

            $exception = $type->getName();
        }

        try {
            ($this->value)();
        } catch (Throwable $e) {
            if (! class_exists($exception)) {
                if ($e instanceof Error && $e->getMessage() === "Class \"$exception\" not found") {
                    throw $e;
                }

                Assert::assertStringContainsString($exception, $e->getMessage(), $failureMessage);

                return $this;
            }

            if ($exceptionMessage !== null) {
                Assert::assertStringContainsString($exceptionMessage, $e->getMessage(), $failureMessage);
            }

            Assert::assertInstanceOf($exception, $e, $failureMessage);
            $callback($e);

            return $this;
        }

        if (! class_exists($exception)) {
            throw new ExpectationFailedException("Exception with message \"$exception\" not thrown.");
        }

        throw new ExpectationFailedException("Exception \"$exception\" not thrown.");
    }

    /**
     * Exports the given value.
     */
    private function export(mixed $value): string
    {
        if ($this->exporter === null) {
            $this->exporter = new Exporter();
        }

        return $this->exporter->export($value);
    }
}
