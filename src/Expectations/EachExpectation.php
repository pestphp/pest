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
    private bool $opposite = false;

    /**
     * @param  Expectation<TValue>  $original
     */
    public function __construct(private readonly Expectation $original) {}

    /**
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
     * @return self<TValue>
     */
    public function not(): self
    {
        $this->opposite = true;

        return $this;
    }

    /**
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
     * @return self<TValue>
     */
    public function __get(string $name): self
    {
        /* @phpstan-ignore-next-line */
        return $this->$name();
    }
}
