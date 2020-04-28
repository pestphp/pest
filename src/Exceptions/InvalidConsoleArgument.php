<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use InvalidArgumentException;
use Symfony\Component\Console\Exception\ExceptionInterface;

/**
 * @internal
 */
final class InvalidConsoleArgument extends InvalidArgumentException implements ExceptionInterface
{
    /**
     * Creates a new instance of should not happen.
     */
    public function __construct(string $message)
    {
        parent::__construct($message, 1);
    }
}
