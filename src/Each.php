<?php

declare(strict_types=1);

namespace Pest;

/**
 * @internal
 *
 * @template TEachValue
 *
 * @mixin Expectation<TEachValue>
 */
final class Each
{
    private bool $opposite = false;

    /**
     * Creates an expectation on each item of the iterable "value".
     *
     * @param Expectation<TEachValue> $original
     */
    public function __construct(private Expectation $original)
    {
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
        return $this->original->and($value);
    }

    /**
     * Creates the opposite expectation for the value.
     *
     * @return self<TEachValue>
     */
    public function not(): Each
    {
        $this->opposite = true;

        return $this;
    }

    /**
     * Dynamically calls methods on the class with the given arguments on each item.
     *
     * @param array<int|string, mixed> $arguments
     *
     * @return self<TEachValue>
     */
    public function __call(string $name, array $arguments): Each
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
     * @return self<TEachValue>
     */
    public function __get(string $name): Each
    {
        /* @phpstan-ignore-next-line */
        return $this->$name();
    }
}
