<?php

declare(strict_types=1);

namespace Pest;

/**
 * @internal
 *
 * @mixin Expectation
 */
final class Each
{
    /**
     * @var Expectation
     */
    private $original;

    /**
     * @var bool
     */
    private $opposite = false;

    /**
     * Creates an expectation on each item of the iterable "value".
     */
    public function __construct(Expectation $original)
    {
        $this->original = $original;
    }

    /**
     * Creates a new expectation.
     *
     * @param mixed $value
     */
    public function and($value): Expectation
    {
        return $this->original->and($value);
    }

    /**
     * Creates the opposite expectation for the value.
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
     */
    public function __get(string $name): Each
    {
        /* @phpstan-ignore-next-line */
        return $this->$name();
    }
}
