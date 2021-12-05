<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use Exception;

/**
 * @internal
 */
final class ExpectationNotFound extends Exception
{
    /**
     * Creates a new ExpectationNotFound instance from the given name.
     */
    public static function fromName(string $name): ExpectationNotFound
    {
        return new self("Expectation [$name] does not exist.");
    }
}
