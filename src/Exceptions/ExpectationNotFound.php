<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use Exception;

/**
 * @internal
 */
final class ExpectationNotFound extends Exception
{
    public static function fromName(string $name): ExpectationNotFound
    {
        return new self("Expectation [$name] does not exist.");
    }
}
