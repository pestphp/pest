<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;
use Pest\Contracts\IsHigherOrderCallable;
use Pest\Expectation;

/**
 * @internal
 */
final class HigherOrderCallables
{
    /**
     * Creates a new Higher Order Callables instances.
     */
    public function __construct(private object $target)
    {
        // ..
    }

    /**
     * @template TValue
     *
     * Create a new expectation. Callable values will be executed prior to returning the new expectation.
     *
     * @param (Closure():TValue)|TValue $value
     *
     * @return Expectation<TValue>
     */
    public function expect(mixed $value): Expectation
    {
        if ($value instanceof IsHigherOrderCallable) {
            $value = fn (...$data) => $value(...$data);
        }

        /** @var TValue $value */
        $value = $value instanceof Closure ? Reflection::bindCallableWithData($value) : $value;

        return new Expectation($value);
    }

    /**
     * @template TValue
     *
     * Create a new expectation. Callable values will be executed prior to returning the new expectation.
     *
     * @param callable|TValue $value
     *
     * @return Expectation<(callable(): mixed)|TValue>
     */
    public function and(mixed $value)
    {
        return $this->expect($value);
    }

    /**
     * Tap into the test case to perform an action and return the test case.
     */
    public function tap(callable $callable): object
    {
        Reflection::bindCallableWithData($callable);

        return $this->target;
    }
}
