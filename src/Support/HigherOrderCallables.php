<?php

declare(strict_types=1);

namespace Pest\Support;

use Pest\Expectation;

/**
 * @internal
 */
final class HigherOrderCallables
{
    /**
     * @var object
     */
    private $target;

    public function __construct(object $target)
    {
        $this->target = $target;
    }

    /**
     * @template TValue
     *
     * Create a new expectation. Callable values will be executed prior to returning the new expectation.
     *
     * @param callable|TValue $value
     *
     * @return Expectation<TValue>
     */
    public function expect($value)
    {
        return new Expectation(is_callable($value) ? Reflection::bindCallable($value) : $value);
    }

    /**
     * @template TValue
     *
     * Create a new expectation. Callable values will be executed prior to returning the new expectation.
     *
     * @param callable|TValue $value
     *
     * @return Expectation<TValue>
     */
    public function and($value)
    {
        return $this->expect($value);
    }

    /**
     * @template TValue
     *
     * @param callable(): TValue $callable
     *
     * @return TValue|object
     */
    public function tap(callable $callable)
    {
        Reflection::bindCallable($callable);

        return $this->target;
    }
}
