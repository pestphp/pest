<?php

declare(strict_types=1);

namespace Pest;

use Pest\Concerns\Expectable;
use Pest\Concerns\RetrievesValues;

/**
 * @internal
 *
 * @mixin Expectation
 */
final class HigherOrderExpectation
{
    use Expectable;
    use RetrievesValues;

    private Expectation|Each $expectation;

    private bool $opposite = false;

    private bool $shouldReset = false;

    /**
     * Creates a new higher order expectation.
     */
    public function __construct(private Expectation $original, mixed $value)
    {
        $this->expectation  = $this->expect($value);
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
     * Creates a new expectation.
     *
     * @template TValue
     *
     * @param TValue $value
     *
     * @return Expectation<TValue>
     */
    public function and(mixed $value): Expectation
    {
        return $this->expect($value);
    }

    /**
     * Dynamically calls methods on the class with the given arguments.
     *
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): self
    {
        if (!$this->expectationHasMethod($name)) {
            /* @phpstan-ignore-next-line */
            return new self($this->original, $this->getValue()->$name(...$arguments));
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

        if (!$this->expectationHasMethod($name)) {
            /** @var array<string, mixed>|object $value */
            $value = $this->getValue();

            return new self($this->original, $this->retrieve($name, $value));
        }

        return $this->performAssertion($name, []);
    }

    /**
     * Determines if the original expectation has the given method name.
     */
    private function expectationHasMethod(string $name): bool
    {
        return method_exists($this->original, $name) || $this->original::hasExtend($name);
    }

    /**
     * Retrieve the applicable value based on the current reset condition.
     */
    private function getValue(): mixed
    {
        return $this->shouldReset ? $this->original->value : $this->expectation->value;
    }

    /**
     * Performs the given assertion with the current expectation.
     *
     * @param array<int, mixed> $arguments
     */
    private function performAssertion(string $name, array $arguments): self
    {
        /* @phpstan-ignore-next-line */
        $this->expectation = ($this->opposite ? $this->expectation->not() : $this->expectation)->{$name}(...$arguments);

        $this->opposite    = false;
        $this->shouldReset = true;

        return $this;
    }
}
