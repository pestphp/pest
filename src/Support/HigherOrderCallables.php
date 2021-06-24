<?php

declare(strict_types=1);

namespace Pest\Support;

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
     * @param callable(): TValue $callable
     *
     * @return TValue|object
     */
    public function tap(callable $callable)
    {
        return Reflection::bindCallable($callable) ?? $this->target;
    }
}
