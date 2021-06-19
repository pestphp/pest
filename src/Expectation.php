<?php

declare(strict_types=1);

namespace Pest;

use BadMethodCallException;
use Pest\Concerns\Extendable;
use Pest\Concerns\RetrievesValues;
use Pest\Support\Arr;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\Exporter\Exporter;

/**
 * @internal
 *
 * @template TValue
 *
 * @property Expectation $not  Creates the opposite expectation.
 * @property Each        $each Creates an expectation on each element on the traversable value.
 */
final class Expectation
{
    use Extendable {
        __call as __extendsCall;
    }
    use RetrievesValues;

    /**
     * The expectation value.
     *
     * @readonly
     *
     * @var mixed
     */
    public $value;

    /**
     * The exporter instance, if any.
     *
     * @readonly
     *
     * @var Exporter|null
     */
    private $exporter;

    /**
     * Creates a new expectation.
     *
     * @param TValue $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * Creates a new expectation.
     *
     * @param TValue $value
     *
     * @return Expectation<TValue>
     */
    public function and($value): Expectation
    {
        return new self($value);
    }

    /**
     * Creates a new expectation with the decoded JSON value.
     */
    public function json(): Expectation
    {
        return $this->toBeJson()->and(json_decode($this->value, true));
    }

    /**
     * Dump the expectation value and end the script.
     *
     * @param mixed $arguments
     *
     * @return never
     */
    public function dd(...$arguments): void
    {
        if (function_exists('dd')) {
            dd($this->value, ...$arguments);
        }

        var_dump($this->value);

        exit(1);
    }

    /**
     * Send the expectation value to Ray along with all given arguments.
     *
     * @param mixed $arguments
     */
    public function ray(...$arguments): self
    {
        if (function_exists('ray')) {
            // @phpstan-ignore-next-line
            ray($this->value, ...$arguments);
        }

        return $this;
    }

    /**
     * Creates the opposite expectation for the value.
     */
    public function not(): OppositeExpectation
    {
        return new OppositeExpectation($this);
    }

    /**
     * Creates an expectation on each item of the iterable "value".
     */
    public function each(callable $callback = null): Each
    {
        if (!is_iterable($this->value)) {
            throw new BadMethodCallException('Expectation value is not iterable.');
        }

        if (is_callable($callback)) {
            foreach ($this->value as $item) {
                $callback(new self($item));
            }
        }

        return new Each($this);
    }

    /**
     * Allows you to specify a sequential set of expectations for each item in a iterable "value".
     *
     * @template TSequenceValue
     *
     * @param callable(self, self): void|TSequenceValue ...$callbacks
     */
    public function sequence(...$callbacks): Expectation
    {
        if (!is_iterable($this->value)) {
            throw new BadMethodCallException('Expectation value is not iterable.');
        }

        $value  = is_array($this->value) ? $this->value : iterator_to_array($this->value);
        $keys   = array_keys($value);
        $values = array_values($value);

        $index = 0;

        while (count($callbacks) < count($values)) {
            $callbacks[] = $callbacks[$index];
            $index       = $index < count($values) - 1 ? $index + 1 : 0;
        }

        foreach ($values as $key => $item) {
            if (is_callable($callbacks[$key])) {
                call_user_func($callbacks[$key], new self($item), new self($keys[$key]));
                continue;
            }

            (new self($item))->toEqual($callbacks[$key]);
        }

        return $this;
    }

    /**
     * Asserts that two variables have the same type and
     * value. Used on objects, it asserts that two
     * variables reference the same object.
     *
     * @param mixed $expected
     */
    public function toBe($expected): Expectation
    {
        Assert::assertSame($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that the value is empty.
     */
    public function toBeEmpty(): Expectation
    {
        Assert::assertEmpty($this->value);

        return $this;
    }

    /**
     * Asserts that the value is true.
     */
    public function toBeTrue(): Expectation
    {
        Assert::assertTrue($this->value);

        return $this;
    }

    /**
     * Asserts that the value is false.
     */
    public function toBeFalse(): Expectation
    {
        Assert::assertFalse($this->value);

        return $this;
    }

    /**
     * Asserts that the value is greater than $expected.
     *
     * @param int|float $expected
     */
    public function toBeGreaterThan($expected): Expectation
    {
        Assert::assertGreaterThan($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that the value is greater than or equal to $expected.
     *
     * @param int|float $expected
     */
    public function toBeGreaterThanOrEqual($expected): Expectation
    {
        Assert::assertGreaterThanOrEqual($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that the value is less than or equal to $expected.
     *
     * @param int|float $expected
     */
    public function toBeLessThan($expected): Expectation
    {
        Assert::assertLessThan($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that the value is less than $expected.
     *
     * @param int|float $expected
     */
    public function toBeLessThanOrEqual($expected): Expectation
    {
        Assert::assertLessThanOrEqual($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that $needle is an element of the value.
     *
     * @param mixed $needle
     */
    public function toContain($needle): Expectation
    {
        if (is_string($this->value)) {
            Assert::assertStringContainsString($needle, $this->value);
        } else {
            Assert::assertContains($needle, $this->value);
        }

        return $this;
    }

    /**
     * Asserts that the value starts with $expected.
     */
    public function toStartWith(string $expected): Expectation
    {
        Assert::assertStringStartsWith($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that the value ends with $expected.
     */
    public function toEndWith(string $expected): Expectation
    {
        Assert::assertStringEndsWith($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that $count matches the number of elements of the value.
     */
    public function toHaveCount(int $count): Expectation
    {
        Assert::assertCount($count, $this->value);

        return $this;
    }

    /**
     * Asserts that the value contains the property $name.
     *
     * @param mixed $value
     */
    public function toHaveProperty(string $name, $value = null): Expectation
    {
        $this->toBeObject();

        Assert::assertTrue(property_exists($this->value, $name));

        if (func_num_args() > 1) {
            /* @phpstan-ignore-next-line */
            Assert::assertEquals($value, $this->value->{$name});
        }

        return $this;
    }

    /**
     * Asserts that two variables have the same value.
     *
     * @param mixed $expected
     */
    public function toEqual($expected): Expectation
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
     * @param mixed $expected
     */
    public function toEqualCanonicalizing($expected): Expectation
    {
        Assert::assertEqualsCanonicalizing($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that the absolute difference between the value and $expected
     * is lower than $delta.
     *
     * @param mixed $expected
     */
    public function toEqualWithDelta($expected, float $delta): Expectation
    {
        Assert::assertEqualsWithDelta($expected, $this->value, $delta);

        return $this;
    }

    /**
     * Asserts that the value is infinite.
     */
    public function toBeInfinite(): Expectation
    {
        Assert::assertInfinite($this->value);

        return $this;
    }

    /**
     * Asserts that the value is an instance of $class.
     *
     * @param string $class
     */
    public function toBeInstanceOf($class): Expectation
    {
        /* @phpstan-ignore-next-line */
        Assert::assertInstanceOf($class, $this->value);

        return $this;
    }

    /**
     * Asserts that the value is an array.
     */
    public function toBeArray(): Expectation
    {
        Assert::assertIsArray($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type bool.
     */
    public function toBeBool(): Expectation
    {
        Assert::assertIsBool($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type callable.
     */
    public function toBeCallable(): Expectation
    {
        Assert::assertIsCallable($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type float.
     */
    public function toBeFloat(): Expectation
    {
        Assert::assertIsFloat($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type int.
     */
    public function toBeInt(): Expectation
    {
        Assert::assertIsInt($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type iterable.
     */
    public function toBeIterable(): Expectation
    {
        Assert::assertIsIterable($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type numeric.
     */
    public function toBeNumeric(): Expectation
    {
        Assert::assertIsNumeric($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type object.
     */
    public function toBeObject(): Expectation
    {
        Assert::assertIsObject($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type resource.
     */
    public function toBeResource(): Expectation
    {
        Assert::assertIsResource($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type scalar.
     */
    public function toBeScalar(): Expectation
    {
        Assert::assertIsScalar($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type string.
     */
    public function toBeString(): Expectation
    {
        Assert::assertIsString($this->value);

        return $this;
    }

    /**
     * Asserts that the value is a JSON string.
     */
    public function toBeJson(): Expectation
    {
        Assert::assertIsString($this->value);
        Assert::assertJson($this->value);

        return $this;
    }

    /**
     * Asserts that the value is NAN.
     */
    public function toBeNan(): Expectation
    {
        Assert::assertNan($this->value);

        return $this;
    }

    /**
     * Asserts that the value is null.
     */
    public function toBeNull(): Expectation
    {
        Assert::assertNull($this->value);

        return $this;
    }

    /**
     * Asserts that the value array has the provided $key.
     *
     * @param string|int $key
     * @param mixed      $value
     */
    public function toHaveKey($key, $value = null): Expectation
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
     */
    public function toHaveKeys(array $keys): Expectation
    {
        foreach ($keys as $key) {
            $this->toHaveKey($key);
        }

        return $this;
    }

    /**
     * Asserts that the value is a directory.
     */
    public function toBeDirectory(): Expectation
    {
        Assert::assertDirectoryExists($this->value);

        return $this;
    }

    /**
     * Asserts that the value is a directory and is readable.
     */
    public function toBeReadableDirectory(): Expectation
    {
        Assert::assertDirectoryIsReadable($this->value);

        return $this;
    }

    /**
     * Asserts that the value is a directory and is writable.
     */
    public function toBeWritableDirectory(): Expectation
    {
        Assert::assertDirectoryIsWritable($this->value);

        return $this;
    }

    /**
     * Asserts that the value is a file.
     */
    public function toBeFile(): Expectation
    {
        Assert::assertFileExists($this->value);

        return $this;
    }

    /**
     * Asserts that the value is a file and is readable.
     */
    public function toBeReadableFile(): Expectation
    {
        Assert::assertFileIsReadable($this->value);

        return $this;
    }

    /**
     * Asserts that the value is a file and is writable.
     */
    public function toBeWritableFile(): Expectation
    {
        Assert::assertFileIsWritable($this->value);

        return $this;
    }

    /**
     * Asserts that the value array matches the given array subset.
     *
     * @param array<int|string, mixed> $array
     */
    public function toMatchArray($array): Expectation
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
     * @param array<string, mixed>|object $object
     */
    public function toMatchObject($object): Expectation
    {
        foreach ((array) $object as $property => $value) {
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
     */
    public function toMatch(string $expression): Expectation
    {
        Assert::assertMatchesRegularExpression($expression, $this->value);

        return $this;
    }

    /**
     * Asserts that the value matches a constraint.
     */
    public function toMatchConstraint(Constraint $constraint): Expectation
    {
        Assert::assertThat($this->value, $constraint);

        return $this;
    }

    /**
     * Exports the given value.
     *
     * @param mixed $value
     */
    private function export($value): string
    {
        if ($this->exporter === null) {
            $this->exporter = new Exporter();
        }

        return $this->exporter->export($value);
    }

    /**
     * Dynamically handle calls to the class or
     * creates a new higher order expectation.
     *
     * @param array<int, mixed> $parameters
     *
     * @return HigherOrderExpectation|mixed
     */
    public function __call(string $method, array $parameters)
    {
        if (!static::hasExtend($method)) {
            /* @phpstan-ignore-next-line */
            return new HigherOrderExpectation($this, $this->value->$method(...$parameters));
        }

        return $this->__extendsCall($method, $parameters);
    }

    /**
     * Dynamically calls methods on the class without any arguments
     * or creates a new higher order expectation.
     *
     * @return Expectation|HigherOrderExpectation
     */
    public function __get(string $name)
    {
        if (!method_exists($this, $name) && !static::hasExtend($name)) {
            return new HigherOrderExpectation($this, $this->retrieve($name, $this->value));
        }

        /* @phpstan-ignore-next-line */
        return $this->{$name}();
    }
}
