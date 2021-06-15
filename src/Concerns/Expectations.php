<?php

declare(strict_types=1);

namespace Pest\Concerns;

use Pest\Expectation;

/**
 * @internal
 */
trait Expectations
{
    /**
     * Creates a new expectation.
     *
     * @param mixed $value
     */
    public function expect($value): Expectation
    {
        return new Expectation($value);
    }
}
