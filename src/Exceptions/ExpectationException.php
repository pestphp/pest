<?php

namespace Pest\Exceptions;

class ExpectationException extends \Exception
{
    public static function invalidValue($expectationName, $valueRequired): ExpectationException
    {
        return new ExpectationException(sprintf('%s expectation requires a %s value.', $expectationName, $valueRequired));
    }
}
