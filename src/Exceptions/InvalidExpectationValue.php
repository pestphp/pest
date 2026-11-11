<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use InvalidArgumentException;

/**
 * @internal
 */
final class InvalidExpectationValue extends InvalidArgumentException
{
    /**
     * @throws self
     */
    public static function expected(string $type): never
    {
        throw new self(sprintf('This expectation may only be used on a value of type [%s].', $type));
    }
}
