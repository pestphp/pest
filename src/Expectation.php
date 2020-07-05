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
