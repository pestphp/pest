<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use InvalidArgumentException;

/**
 * @internal
 */
final class InvalidConsoleArgument extends InvalidArgumentException
{
    /**
     * Creates a new instance of should not happen.
     */
    public function __construct(string $message)
    {
        parent::__construct($message, 1);
    }
}
