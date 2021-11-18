<?php

declare(strict_types=1);

namespace Pest\Exceptions;

/**
 * @internal
 */
final class ExpectationException extends \Exception
{
    public static function invalidCurrentValueType(string $expectationName, string $valueRequired): ExpectationException
    {
        return new ExpectationException(sprintf('%s expectation requires a %s value.', $expectationName, $valueRequired));
    }

    public static function invalidExpectedValueType(string $expectationName, string $valueRequired): ExpectationException
    {
        return new ExpectationException(sprintf('%s expectation requires a %s as expected value.', $expectationName, $valueRequired));
    }
}
