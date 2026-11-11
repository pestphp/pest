<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use RuntimeException;
use Throwable;

/**
 * @internal
 */
final class DatasetProviderError extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct($previous->getMessage(), (int) $previous->getCode(), $previous);
    }
}
