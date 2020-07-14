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
     * @param mixed $value
     */
    public function toBe($value): Expectation
    {
        Assert::assertSame($value, $this->value);

        return $this;
    }

    /**
     * Assert the value is empty.
     */
    public function toBeEmpty(): Expectation
    {
        Assert::assertEmpty($this->value);

        return $this;
    }

    /**
     * Assert the value is false.
     */
    public function toBeFalse(): Expectation
    {
        Assert::assertFalse($this->value);

        return $this;
    }

    /**
     * Assert the value is greater than expected one.
     *
     * @param int|float $value
     */
    public function toBeGreaterThan($value): Expectation
    {
        Assert::assertGreaterThan($value, $this->value);

        return $this;
    }

    /**
     * Assert that value is greater than or equal to the expected one.
     *
     * @param int|float $value
     */
    public function toBeGreaterThanOrEqual($value): Expectation
    {
        Assert::assertGreaterThanOrEqual($value, $this->value);

        return $this;
    }

    /**
     * Assert that value is less than or equal to the expected one.
     *
     * @param int|float $value
     */
    public function toBeLessThan($value): Expectation
    {
        Assert::assertLessThan($value, $this->value);

        return $this;
    }

    /**
     * Assert that value is less than the expected one.
     *
     * @param int|float $value
     */
    public function toBeLessThanOrEqual($value): Expectation
    {
        Assert::assertLessThanOrEqual($value, $this->value);

        return $this;
    }

    /**
     * Assert that needles is an element of value.
     *
     * @param mixed $needle
     */
    public function toContain($needle): Expectation
    {
        Assert::assertContains($needle, $this->value);

        return $this;
    }

    /**
     * Assert the value contains only variables of type.
     *
     * @param mixed $type
     */
    public function toContainOnly($type): Expectation
    {
        Assert::assertContainsOnly($type, $this->value);

        return $this;
    }

    /**
     * Assert the value contains only instances of $instance.
     */
    public function toContainOnlyInstancesOf(string $instance): Expectation
    {
        Assert::assertContainsOnlyInstancesOf($instance, $this->value);

        return $this;
    }

    /**
     * Assert that needles is a substring of value.
     */
    public function toContainString(string $needle): Expectation
    {
        Assert::assertStringContainsString($needle, $this->value);

        return $this;
    }

    /**
     * Assert that needles is a substring of value, ignoring the
     * difference in casing.
     */
    public function toContainStringIgnoringCase(string $needle): Expectation
    {
        Assert::assertStringContainsStringIgnoringCase($needle, $this->value);

        return $this;
    }

    /**
     * Assert that $count matches the number of elements of $value.
     */
    public function toCount(int $count): Expectation
    {
        Assert::assertCount($count, $this->value);

        return $this;
    }

    /**
     * Asserts that two variables have the same value.
     *
     * @param mixed $value
     */
    public function toEqual($value): Expectation
    {
        Assert::assertEquals($value, $this->value);

        return $this;
    }

    /**
     * Asserts that two variables are equals, ignoring the casing
     * for the comparison.
     *
     * @param mixed $value
     */
    public function toEqualIgnoringCase($value): Expectation
    {
        Assert::assertEqualsIgnoringCase($value, $this->value);

        return $this;
    }

    /**
     * Asserts that two variables have the same value.
     * The contents of $expected and $actual are canonicalized before
     * they are compared. For instance, when the two variables $value and
     * $this->value are arrays, then these arrays are sorted before they are
     * compared. When $value and $this->value are objects,
     * each object is converted to an array containing all private,
     * protected and public attributes.
     *
     * @param mixed $value
     */
    public function toEqualCanonicalizing($value): Expectation
    {
        Assert::assertEqualsCanonicalizing($value, $this->value);

        return $this;
    }

    /**
     * Assert that the absolute difference between $value and $this->value
     * is greater than $delta.
     *
     * @param mixed $value
     */
    public function toEqualWithDelta($value, float $delta): Expectation
    {
        Assert::assertEqualsWithDelta($value, $this->value, $delta);

        return $this;
    }

    /**
     * Assert that the value infinite.
     */
    public function toBeInfinite(): Expectation
    {
        Assert::assertInfinite($this->value);

        return $this;
    }

    /**
     * Assert that the value is an instance of $value.
     *
     * @param mixed $value
     */
    public function toBeInstanceOf($value): Expectation
    {
        Assert::assertInstanceOf($value, $this->value);

        return $this;
    }

    /**
     * Assert that the value is an array.
     */
    public function toBeArray(): Expectation
    {
        Assert::assertIsArray($this->value);

        return $this;
    }

    /**
     * Assert that the value is of type bool.
     */
    public function toBeBool(): Expectation
    {
        Assert::assertIsBool($this->value);

        return $this;
    }

    /**
     * Assert that the value is of type callable.
     */
    public function toBeCallable(): Expectation
    {
        Assert::assertIsCallable($this->value);

        return $this;
    }

    /**
     * Assert that the value is type of float.
     */
    public function toBeFloat(): Expectation
    {
        Assert::assertIsFloat($this->value);

        return $this;
    }

    /**
     * Assert that the value is type of int.
     */
    public function toBeInt(): Expectation
    {
        Assert::assertIsInt($this->value);

        return $this;
    }

    /**
     * Assert that the value is type of iterable.
     */
    public function toBeIterable(): Expectation
    {
        Assert::assertIsIterable($this->value);

        return $this;
    }

    /**
     * Assert that the value is type of numeric.
     */
    public function toBeNumeric(): Expectation
    {
        Assert::assertIsNumeric($this->value);

        return $this;
    }

    /**
     * Assert that the value is type of object.
     */
    public function toBeObject(): Expectation
    {
        Assert::assertIsObject($this->value);

        return $this;
    }

    /**
     * Assert that the value is type of resource.
     */
    public function toBeResource(): Expectation
    {
        Assert::assertIsResource($this->value);

        return $this;
    }

    /**
     * Assert that the value is type of scalar.
     */
    public function toBeScalar(): Expectation
    {
        Assert::assertIsScalar($this->value);

        return $this;
    }

    /**
     * Assert that the value is type of string.
     */
    public function toBeString(): Expectation
    {
        Assert::assertIsString($this->value);

        return $this;
    }

    /**
     * Assert that the value is NAN.
     */
    public function toBeNan(): Expectation
    {
        Assert::assertNan($this->value);

        return $this;
    }

    /**
     * Assert that the value is NAN.
     */
    public function toBeNull(): Expectation
    {
        Assert::assertNull($this->value);

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
