<?php

declare(strict_types=1);

namespace Pest\Concerns;

use Pest\Expectation;

/**
 * @internal
 */
trait Expectable
{
    /**
     * @template TValue
     *
     * Creates a new expectation.
     *
     * @param TValue $value
     *
     * @return Expectation<TValue>
     */
    public function expect($value): Expectation
    {
        return new Expectation($value);
    }
}
