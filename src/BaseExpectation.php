<?php

declare(strict_types=1);

namespace Pest;

use BadMethodCallException;
use Closure;
use InvalidArgumentException;
use Pest\Concerns\Retrievable;
use Pest\Exceptions\InvalidExpectationValue;
use Pest\Support\Arr;
use Pest\Support\NullClosure;
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
 * @mixin Expectation<TValue>
 */
final class BaseExpectation
{
    /**
     * The exporter instance, if any.
     *
     * @readonly
     */
    private Exporter|null $exporter = null;

    /**
     * Creates a new expectation.
     *
     * @param TValue $value
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
     * @return BaseExpectation<TValue>
     */
    public function toBe(mixed $expected): BaseExpectation
    {
        Assert::assertSame($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that the value is empty.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeEmpty(): BaseExpectation
    {
        Assert::assertEmpty($this->value);

        return $this;
    }

    /**
     * Asserts that the value is true.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeTrue(): BaseExpectation
    {
        Assert::assertTrue($this->value);

        return $this;
    }

    /**
     * Asserts that the value is truthy.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeTruthy(): BaseExpectation
    {
        Assert::assertTrue((bool) $this->value);

        return $this;
    }

    /**
     * Asserts that the value is false.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeFalse(): BaseExpectation
    {
        Assert::assertFalse($this->value);

        return $this;
    }

    /**
     * Asserts that the value is falsy.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeFalsy(): BaseExpectation
    {
        Assert::assertFalse((bool) $this->value);

        return $this;
    }

    /**
     * Asserts that the value is greater than $expected.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeGreaterThan(int|float $expected): BaseExpectation
    {
        Assert::assertGreaterThan($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that the value is greater than or equal to $expected.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeGreaterThanOrEqual(int|float $expected): BaseExpectation
    {
        Assert::assertGreaterThanOrEqual($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that the value is less than or equal to $expected.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeLessThan(int|float $expected): BaseExpectation
    {
        Assert::assertLessThan($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that the value is less than $expected.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeLessThanOrEqual(int|float $expected): BaseExpectation
    {
        Assert::assertLessThanOrEqual($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that $needle is an element of the value.
     *
     * @return BaseExpectation<TValue>
     */
    public function toContain(mixed ...$needles): BaseExpectation
    {
        foreach ($needles as $needle) {
            if (is_string($this->value)) {
                // @phpstan-ignore-next-line
                Assert::assertStringContainsString((string) $needle, $this->value);
            } else {
                if (!is_iterable($this->value)) {
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
     * @param non-empty-string $expected
     *
     *@return BaseExpectation<TValue>
     */
    public function toStartWith(string $expected): BaseExpectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertStringStartsWith($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that the value ends with $expected.
     *
     * @param non-empty-string $expected
     *
     *@return BaseExpectation<TValue>
     */
    public function toEndWith(string $expected): BaseExpectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertStringEndsWith($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that $number matches value's Length.
     *
     * @return BaseExpectation<TValue>
     */
    public function toHaveLength(int $number): BaseExpectation
    {
        if (is_string($this->value)) {
            Assert::assertEquals($number, mb_strlen($this->value));

            return $this;
        }

        if (is_iterable($this->value)) {
            return $this->toHaveCount($number);
        }

        if (is_object($this->value)) {
            if (method_exists($this->value, 'toArray')) {
                $array = $this->value->toArray();
            } else {
                $array = (array) $this->value;
            }

            Assert::assertCount($number, $array);

            return $this;
        }

        throw new BadMethodCallException('Expectation value length is not countable.');
    }

    /**
     * Asserts that $count matches the number of elements of the value.
     *
     * @return BaseExpectation<TValue>
     */
    public function toHaveCount(int $count): BaseExpectation
    {
        if (!is_countable($this->value) && !is_iterable($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertCount($count, $this->value);

        return $this;
    }

    /**
     * Asserts that the value contains the property $name.
     *
     * @return BaseExpectation<TValue>
     */
    public function toHaveProperty(string $name, mixed $value = null): BaseExpectation
    {
        $this->toBeObject();

        //@phpstan-ignore-next-line
        Assert::assertTrue(property_exists($this->value, $name));

        if (func_num_args() > 1) {
            /* @phpstan-ignore-next-line */
            Assert::assertEquals($value, $this->value->{$name});
        }

        return $this;
    }

    /**
     * Asserts that the value contains the provided properties $names.
     *
     * @param iterable<array-key, string> $names
     *
     *@return BaseExpectation<TValue>
     */
    public function toHaveProperties(iterable $names): BaseExpectation
    {
        foreach ($names as $name) {
            $this->toHaveProperty($name);
        }

        return $this;
    }

    /**
     * Asserts that two variables have the same value.
     *
     * @return BaseExpectation<TValue>
     */
    public function toEqual(mixed $expected): BaseExpectation
    {
        Assert::assertEquals($expected, $this->value);

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
     * @return BaseExpectation<TValue>
     */
    public function toEqualCanonicalizing(mixed $expected): BaseExpectation
    {
        Assert::assertEqualsCanonicalizing($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that the absolute difference between the value and $expected
     * is lower than $delta.
     *
     * @return BaseExpectation<TValue>
     */
    public function toEqualWithDelta(mixed $expected, float $delta): BaseExpectation
    {
        Assert::assertEqualsWithDelta($expected, $this->value, $delta);

        return $this;
    }

    /**
     * Asserts that the value is one of the given values.
     *
     * @param iterable<int|string, mixed> $values
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeIn(iterable $values): BaseExpectation
    {
        Assert::assertContains($this->value, $values);

        return $this;
    }

    /**
     * Asserts that the value is infinite.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeInfinite(): BaseExpectation
    {
        Assert::assertInfinite($this->value);

        return $this;
    }

    /**
     * Asserts that the value is an instance of $class.
     *
     * @param class-string $class
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeInstanceOf(string $class): BaseExpectation
    {
        Assert::assertInstanceOf($class, $this->value);

        return $this;
    }

    /**
     * Asserts that the value is an array.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeArray(): BaseExpectation
    {
        Assert::assertIsArray($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type bool.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeBool(): BaseExpectation
    {
        Assert::assertIsBool($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type callable.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeCallable(): BaseExpectation
    {
        Assert::assertIsCallable($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type float.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeFloat(): BaseExpectation
    {
        Assert::assertIsFloat($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type int.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeInt(): BaseExpectation
    {
        Assert::assertIsInt($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type iterable.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeIterable(): BaseExpectation
    {
        Assert::assertIsIterable($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type numeric.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeNumeric(): BaseExpectation
    {
        Assert::assertIsNumeric($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type object.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeObject(): BaseExpectation
    {
        Assert::assertIsObject($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type resource.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeResource(): BaseExpectation
    {
        Assert::assertIsResource($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type scalar.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeScalar(): BaseExpectation
    {
        Assert::assertIsScalar($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type string.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeString(): BaseExpectation
    {
        Assert::assertIsString($this->value);

        return $this;
    }

    /**
     * Asserts that the value is a JSON string.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeJson(): BaseExpectation
    {
        Assert::assertIsString($this->value);

        //@phpstan-ignore-next-line
        Assert::assertJson($this->value);

        return $this;
    }

    /**
     * Asserts that the value is NAN.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeNan(): BaseExpectation
    {
        Assert::assertNan($this->value);

        return $this;
    }

    /**
     * Asserts that the value is null.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeNull(): BaseExpectation
    {
        Assert::assertNull($this->value);

        return $this;
    }

    /**
     * Asserts that the value array has the provided $key.
     *
     * @return BaseExpectation<TValue>
     */
    public function toHaveKey(string|int $key, mixed $value = null): BaseExpectation
    {
        if (is_object($this->value) && method_exists($this->value, 'toArray')) {
            $array = $this->value->toArray();
        } else {
            $array = (array) $this->value;
        }

        try {
            Assert::assertTrue(Arr::has($array, $key));

            /* @phpstan-ignore-next-line  */
        } catch (ExpectationFailedException $exception) {
            throw new ExpectationFailedException("Failed asserting that an array has the key '$key'", $exception->getComparisonFailure());
        }

        if (func_num_args() > 1) {
            Assert::assertEquals($value, Arr::get($array, $key));
        }

        return $this;
    }

    /**
     * Asserts that the value array has the provided $keys.
     *
     * @param array<int, int|string> $keys
     *
     * @return BaseExpectation<TValue>
     */
    public function toHaveKeys(array $keys): BaseExpectation
    {
        foreach ($keys as $key) {
            $this->toHaveKey($key);
        }

        return $this;
    }

    /**
     * Asserts that the value is a directory.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeDirectory(): BaseExpectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertDirectoryExists($this->value);

        return $this;
    }

    /**
     * Asserts that the value is a directory and is readable.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeReadableDirectory(): BaseExpectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertDirectoryIsReadable($this->value);

        return $this;
    }

    /**
     * Asserts that the value is a directory and is writable.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeWritableDirectory(): BaseExpectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertDirectoryIsWritable($this->value);

        return $this;
    }

    /**
     * Asserts that the value is a file.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeFile(): BaseExpectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertFileExists($this->value);

        return $this;
    }

    /**
     * Asserts that the value is a file and is readable.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeReadableFile(): BaseExpectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertFileIsReadable($this->value);

        return $this;
    }

    /**
     * Asserts that the value is a file and is writable.
     *
     * @return BaseExpectation<TValue>
     */
    public function toBeWritableFile(): BaseExpectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }
        Assert::assertFileIsWritable($this->value);

        return $this;
    }

    /**
     * Asserts that the value array matches the given array subset.
     *
     * @param iterable<int|string, mixed> $array
     *
     * @return BaseExpectation<TValue>
     */
    public function toMatchArray(iterable|object $array): BaseExpectation
    {
        if (is_object($this->value) && method_exists($this->value, 'toArray')) {
            $valueAsArray = $this->value->toArray();
        } else {
            $valueAsArray = (array) $this->value;
        }

        foreach ($array as $key => $value) {
            Assert::assertArrayHasKey($key, $valueAsArray);

            Assert::assertEquals(
                $value,
                $valueAsArray[$key],
                sprintf(
                    'Failed asserting that an array has a key %s with the value %s.',
                    $this->export($key),
                    $this->export($valueAsArray[$key]),
                ),
            );
        }

        return $this;
    }

    /**
     * Asserts that the value object matches a subset
     * of the properties of an given object.
     *
     * @param iterable<string, mixed>|object $object
     *
     * @return BaseExpectation<TValue>
     */
    public function toMatchObject(iterable|object $object): BaseExpectation
    {
        foreach ((array) $object as $property => $value) {
            if (!is_object($this->value) && !is_string($this->value)) {
                InvalidExpectationValue::expected('object|string');
            }

            Assert::assertTrue(property_exists($this->value, $property));

            /* @phpstan-ignore-next-line */
            $propertyValue = $this->value->{$property};
            Assert::assertEquals(
                $value,
                $propertyValue,
                sprintf(
                    'Failed asserting that an object has a property %s with the value %s.',
                    $this->export($property),
                    $this->export($propertyValue),
                ),
            );
        }

        return $this;
    }

    /**
     * Asserts that the value matches a regular expression.
     *
     * @return BaseExpectation<TValue>
     */
    public function toMatch(string $expression): BaseExpectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }
        Assert::assertMatchesRegularExpression($expression, $this->value);

        return $this;
    }

    /**
     * Asserts that the value matches a constraint.
     *
     * @return BaseExpectation<TValue>
     */
    public function toMatchConstraint(Constraint $constraint): BaseExpectation
    {
        Assert::assertThat($this->value, $constraint);

        return $this;
    }

    /**
     * Asserts that executing value throws an exception.
     *
     * @param (Closure(Throwable): mixed)|string $exception
     *
     * @return BaseExpectation<TValue>
     */
    public function toThrow(callable|string $exception, string $exceptionMessage = null): BaseExpectation
    {
        $callback = NullClosure::create();

        if ($exception instanceof Closure) {
            $callback   = $exception;
            $parameters = (new ReflectionFunction($exception))->getParameters();

            if (1 !== count($parameters)) {
                throw new InvalidArgumentException('The given closure must have a single parameter type-hinted as the class string.');
            }

            if (!($type = $parameters[0]->getType()) instanceof ReflectionNamedType) {
                throw new InvalidArgumentException('The given closure\'s parameter must be type-hinted as the class string.');
            }

            $exception = $type->getName();
        }

        try {
            ($this->value)();
        } catch (Throwable $e) { // @phpstan-ignore-line
            if (!class_exists($exception)) {
                Assert::assertStringContainsString($exception, $e->getMessage());

                return $this;
            }

            if ($exceptionMessage !== null) {
                Assert::assertStringContainsString($exceptionMessage, $e->getMessage());
            }

            Assert::assertInstanceOf($exception, $e);
            $callback($e);

            return $this;
        }

        if (!class_exists($exception)) {
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
