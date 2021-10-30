<?php

namespace Pest\Exceptions;

use InvalidArgumentException;

/**
 * @internal
 */
final class ExpectationNotFoundException extends InvalidArgumentException
{
    public function __construct(string $expectationName)
    {
        parent::__construct(sprintf("Impossible to find [%s] expectation", $expectationName));
    }

}
