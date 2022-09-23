<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;
use Pest\Expectation;

/**
 * @internal
 */
final class HigherOrderCallables
{
    /**
     * Creates a new Higher Order Callables instances.
     */
    public function __construct(private readonly object $target)
    {
        // ..
    }

    /**
     * @template TValue
     *
     * Create a new expectation. Callable values will be executed prior to returning the new expectation.
     *
     * @param (Closure():TValue)|TValue|null $value
     * @return Expectation<TValue>
     */
    public function expect(mixed $value=null): Expectation
    {
        /** @var TValue $value */
        $value = ($value instanceof Closure ? Reflection::bindCallableWithData($value) : $value) ?? $this->target;

        return new Expectation($value);
    }

    /**
     * @template TValue
     *
     * Create a new expectation. Callable values will be executed prior to returning the new expectation.
     *
     * @param  callable|TValue  $value
     * @return Expectation<(callable(): mixed)|TValue>
     */
    public function and(mixed $value): Expectation
    {
        return $this->expect($value);
    }

    /**
     * Execute the given callable after the test has executed the setup method.
     */
    public function defer(callable $callable): object
    {
        Reflection::bindCallableWithData($callable);

        return $this->target;
    }
}
