<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;
use Pest\Expectation;
use Pest\PendingObjects\TestCall;
use PHPUnit\Framework\TestCase;

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
        return new Expectation($value instanceof Closure ? Reflection::bindCallableWithData($value) : $value);
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
     * Tap into the test case to perform an action and return the test case.
     *
     * @return TestCall|TestCase|object
     */
    public function tap(callable $callable)
    {
        Reflection::bindCallableWithData($callable);

        return $this->target;
    }
}
