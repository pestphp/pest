<?php

declare(strict_types=1);

namespace Pest\Expectations;

use Closure;
use Pest\Concerns\Retrievable;
use Pest\Expectation;

/**
 * @internal
 *
 * @template TOriginalValue
 * @template TValue
 *
 * @mixin Expectation<TOriginalValue>
 */
final class HigherOrderExpectation
{
    use Retrievable;

    /**
     * @var Expectation<TValue>|EachExpectation<TValue>
     */
    private Expectation|EachExpectation $expectation;

    /**
     * Indicates if the expectation is the opposite.
     */
    private bool $opposite = false;

    /**
     * Indicates if the expectation should reset the value.
     */
    private bool $shouldReset = false;

    /**
     * Creates a new higher order expectation.
     *
     * @param  Expectation<TOriginalValue>  $original
     * @param  TValue  $value
     */
    public function __construct(private readonly Expectation $original, mixed $value)
    {
        $this->expectation = $this->expect($value);
    }

    /**
     * Creates the opposite expectation for the value.
     *
     * @return self<TOriginalValue, TValue>
     */
    public function not(): self
    {
        $this->opposite = ! $this->opposite;

        return $this;
    }

    /**
     * Creates a new Expectation.
     *
     * @template TExpectValue
     *
     * @param  TExpectValue  $value
     * @return Expectation<TExpectValue>
     */
    public function expect(mixed $value): Expectation
    {
        return new Expectation($value);
    }

    /**
     * Creates a new expectation.
     *
     * @template TExpectValue
     *
     * @param  TExpectValue  $value
     * @return Expectation<TExpectValue>
     */
    public function and(mixed $value): Expectation
    {
        return $this->expect($value);
    }

    /**
     * Scope an expectation callback to the current value in
     * the HigherOrderExpectation chain.
     *
     * @param  Closure(Expectation<TValue>): void  $expectation
     * @return HigherOrderExpectation<TOriginalValue, TOriginalValue>
     */
    public function scoped(Closure $expectation): self
    {
        $expectation->__invoke($this->expectation);

        return new self($this->original, $this->original->value);
    }

    /**
     * Creates a new expectation with the decoded JSON value.
     *
     * @return self<TOriginalValue, array<string|int, mixed>|bool>
     */
    public function json(): self
    {
        return new self($this->original, $this->expectation->json()->value);
    }

    /**
     * Dynamically calls methods on the class with the given arguments.
     *
     * @param  array<int, mixed>  $arguments
     * @return self<TOriginalValue, mixed>|self<TOriginalValue, TValue>
     */
    public function __call(string $name, array $arguments): self
    {
        if (! $this->expectationHasMethod($name)) {
            /* @phpstan-ignore-next-line */
            return new self($this->original, $this->getValue()->$name(...$arguments));
        }

        return $this->performAssertion($name, $arguments);
    }

    /**
     * Accesses properties in the value or in the expectation.
     *
     * @return self<TOriginalValue, mixed>|self<TOriginalValue, TValue>
     */
    public function __get(string $name): self
    {
        if ($name === 'not') {
            return $this->not();
        }

        if (! $this->expectationHasMethod($name)) {
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
        if (method_exists($this->original, $name)) {
            return true;
        }
        if ($this->original::hasMethod($name)) {
            return true;
        }

        return $this->original::hasExtend($name);
    }

    /**
     * Retrieve the applicable value based on the current reset condition.
     *
     * @return TOriginalValue|TValue
     */
    private function getValue(): mixed
    {
        return $this->shouldReset ? $this->original->value : $this->expectation->value;
    }

    /**
     * Performs the given assertion with the current expectation.
     *
     * @param  array<int, mixed>  $arguments
     * @return self<TOriginalValue, TValue>
     */
    private function performAssertion(string $name, array $arguments): self
    {
        /* @phpstan-ignore-next-line */
        $this->expectation = ($this->opposite ? $this->expectation->not() : $this->expectation)->{$name}(...$arguments);

        $this->opposite = false;
        $this->shouldReset = true;

        return $this;
    }
}
