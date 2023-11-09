<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use Exception;

/**
 * @internal
 */
final class InvalidMethod extends Exception
{
    /**
     * Creates a new InvalidMethod instance from the given name.
     */
    public static function fromName(string $name): InvalidMethod
    {
        return new self("Method [$name] does not exist.");
    }
}
