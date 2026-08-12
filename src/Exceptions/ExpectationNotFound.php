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
        return new self("The expectation [$name] does not exist. You may register it using [expect()->extend()].");
    }
}
