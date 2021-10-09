<?php

namespace Pest\Exceptions;

use Exception;

class PipeException extends Exception
{
    public static function optionalParmetersShouldBecomeRequired(string $expectationName): PipeException
    {
        return new self("You're attempting to pipe '$expectationName', but in pipelines optional parmeters should be declared as required)");
    }

    public static function expectationNotFound($expectationName): PipeException
    {
        return new self("Expectation $expectationName was not found in Pest");
    }
}
