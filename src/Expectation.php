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
     * Assert that value is false.
     */
    public function toBeFalse()
    {
        Assert::assertFalse($this->value);

        return $this;
    }

    /**
     * Assert that value is greater than expected one.
     *
     * @param int|float $value
     */
    public function toBeGreaterThan($value)
    {
        Assert::assertGreaterThan($value, $this->value);

        return $this;
    }

    /**
     * Assert that value is greater than or equal to the expected one.
     *
     * @param int|float $value
     */
    public function toBeGreaterThanOrEqual($value)
    {
        Assert::assertGreaterThanOrEqual($value, $this->value);

        return $this;
    }

    /**
     * Assert that value is less than or equal to the expected one.
     *
     * @param int|float $value
     */
    public function toBeLessThan($value)
    {
        Assert::assertLessThan($value, $this->value);

        return $this;
    }

    /**
     * Assert that value is less than the expected one.
     *
     * @param int|float $value
     */
    public function toBeLessThanOrEqual($value)
    {
        Assert::assertLessThanOrEqual($value, $this->value);

        return $this;
    }

    /**
     * Assert that needles is an element of value.
     *
     * @param mixed $needle
     */
    public function toContain($needle)
    {
        Assert::assertContains($needle, $this->value);

        return $this;
    }

    /**
     * Assert that needles is a substring of value.
     *
     * @param string $needle
     */
    public function toContainString(string $needle)
    {
        Assert::assertStringContainsString($needle, $this->value);

        return $this;
    }

    /**
     * Assert that needles is a substring of value, ignoring the
     * difference in casing.
     *
     * @param string $needle
     */
    public function toContainStringIgnoringCase(string $needle)
    {
        Assert::assertStringContainsStringIgnoringCase($needle, $this->value);

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
