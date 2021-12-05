<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use Exception;

final class PipeException extends Exception
{
    public static function expectationNotFound(string $expectationName): PipeException
    {
        return new self("Expectation $expectationName was not found in Pest");
    }
}
