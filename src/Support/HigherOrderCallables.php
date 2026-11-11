<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;
use Pest\Expectation;

/**
 * @internal
 */
final readonly class HigherOrderCallables
{
    public function __construct(private object $target)
    {
        //
    }

    /**
     * @template TValue
     *
     * @param  (Closure():TValue)|TValue  $value
     * @return Expectation<TValue>
     */
    public function expect(mixed $value): Expectation
    {
        /** @var TValue $value */
        $value = $value instanceof Closure ? Reflection::bindCallableWithData($value) : $value;

        return new Expectation($value);
    }

    /**
     * @template TValue
     *
     * @param  callable|TValue  $value
     * @return Expectation<(callable(): mixed)|TValue>
     */
    public function and(mixed $value): Expectation
    {
        // @phpstan-ignore-next-line
        return $this->expect($value);
    }

    public function defer(callable $callable): object
    {
        Reflection::bindCallableWithData($callable);

        return $this->target;
    }
}
