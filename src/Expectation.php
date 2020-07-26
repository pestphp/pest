<?php

declare(strict_types=1);

namespace Pest;

use PHPUnit\Framework\Assert;

/**
 * @internal
 *
 * @property Expectation $not Creates the opposite expectation.
 */
final class Expectation
{
    /**
     * The expectation value.
     *
     * @var mixed
     */
    private $value;

    /**
     * Creates a new expectation.
     *
     * @param mixed $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * Creates a new expectation.
     *
     * @param mixed $value
     */
    public function and($value): Expectation
    {
        return new self($value);
    }

    /**
     * Creates the opposite expectation for the value.
     */
    public function not(): OppositeExpectation
    {
        return new OppositeExpectation($this);
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
     * Asserts that $count matches the number of elements of the value.
     */
    public function toHaveCount(int $count): Expectation
    {
        Assert::assertCount($count, $this->value);

        return $this;
    }

    /**
     * Asserts that the value contains the property $name.
     */
    public function toHaveProperty(string $name): Expectation
    {
        Assert::assertTrue(property_exists($this->value, $name));

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
     */
    public function toHaveKey(string $key): Expectation
    {
        Assert::assertArrayHasKey($key, $this->value);

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
     * Dynamically calls methods on the class without any arguments.
     *
     * @return Expectation
     */
    public function __get(string $name)
    {
        /* @phpstan-ignore-next-line */
        return $this->{$name}();
    }
}
