<?php

declare(strict_types=1);

namespace Pest\Expectations;

use Pest\Expectation;

use function expect;

/**
 * @internal
 *
 * @template TValue
 *
 * @mixin Expectation<TValue>
 */
final class EachExpectation
{
    /**
     * Indicates if the expectation is the opposite.
     */
    private bool $opposite = false;

    /**
     * Creates an expectation on each item of the iterable "value".
     *
     * @param  Expectation<TValue>  $original
     */
    public function __construct(private readonly Expectation $original) {}

    /**
     * Creates a new expectation.
     *
     * @template TAndValue
     *
     * @param  TAndValue  $value
     * @return Expectation<TAndValue>
     */
    public function and(mixed $value): Expectation
    {
        return $this->original->and($value);
    }

    /**
     * Creates the opposite expectation for the value.
     *
     * @return self<TValue>
     */
    public function not(): self
    {
        $this->opposite = true;

        return $this;
    }

    /**
     * Dynamically calls methods on the class with the given arguments on each item.
     *
     * @param  array<int|string, mixed>  $arguments
     * @return self<TValue>
     */
    public function __call(string $name, array $arguments): self
    {
        foreach ($this->original->value as $item) {
            /* @phpstan-ignore-next-line */
            $this->opposite ? expect($item)->not()->$name(...$arguments) : expect($item)->$name(...$arguments);
        }

        $this->opposite = false;

        return $this;
    }

    /**
     * Dynamically calls methods on the class without any arguments on each item.
     *
     * @return self<TValue>
     */
    public function __get(string $name): self
    {
        /* @phpstan-ignore-next-line */
        return $this->$name();
    }
}
