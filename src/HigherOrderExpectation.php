<?php

declare(strict_types=1);

namespace Pest;

use Pest\Concerns\Expectable;

/**
 * @internal
 *
 * @mixin Expectation
 */
final class HigherOrderExpectation
{
    use Expectable;

    /**
     * @var Expectation
     */
    private $original;

    /**
     * @var Expectation|Each
     */
    private $expectation;

    /**
     * @var bool
     */
    private $opposite = false;

    /**
     * @var string
     */
    private $name;

    /**
     * Creates a new higher order expectation.
     *
     * @param array<int|string, mixed>|null $parameters
     * @phpstan-ignore-next-line
     */
    public function __construct(Expectation $original, string $name, ?array $parameters = null)
    {
        $this->original = $original;
        $this->name     = $name;

        $this->expectation = $this->expect(
            is_null($parameters) ? $this->getPropertyValue() : $this->getMethodValue($parameters)
        );
    }

    /**
     * Retrieves the property value from the original expectation.
     *
     * @return mixed
     */
    private function getPropertyValue()
    {
        if (is_array($this->original->value)) {
            return $this->original->value[$this->name];
        }

        // @phpstan-ignore-next-line
        return $this->original->value->{$this->name};
    }

    /**
     * Retrieves the value of the method from the original expectation.
     *
     * @param array<int|string, mixed> $arguments
     *
     * @return mixed
     */
    private function getMethodValue(array $arguments)
    {
        // @phpstan-ignore-next-line
        return $this->original->value->{$this->name}(...$arguments);
    }

    /**
     * Creates the opposite expectation for the value.
     */
    public function not(): HigherOrderExpectation
    {
        $this->opposite = !$this->opposite;

        return $this;
    }

    /**
     * Dynamically calls methods on the class with the given arguments.
     *
     * @param array<int|string, mixed> $arguments
     */
    public function __call(string $name, array $arguments): self
    {
        if (!$this->originalHasMethod($name)) {
            return new self($this->original, $name, $arguments);
        }

        return $this->performAssertion($name, $arguments);
    }

    /**
     * Accesses properties in the value or in the expectation.
     */
    public function __get(string $name): self
    {
        if ($name === 'not') {
            return $this->not();
        }

        if (!$this->originalHasMethod($name)) {
            return new self($this->original, $name);
        }

        return $this->performAssertion($name, []);
    }

    /**
     * Determines if the original expectation has the given method name.
     */
    private function originalHasMethod(string $name): bool
    {
        return method_exists($this->original, $name) || $this->original::hasExtend($name);
    }

    /**
     * Performs the given assertion with the current expectation.
     *
     * @param array<int|string, mixed> $arguments
     */
    private function performAssertion(string $name, array $arguments): self
    {
        $expectation = $this->opposite
            ? $this->expectation->not()
            : $this->expectation;

        $this->expectation = $expectation->{$name}(...$arguments); // @phpstan-ignore-line

        $this->opposite = false;

        return $this;
    }
}
