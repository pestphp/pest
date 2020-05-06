<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use InvalidArgumentException;
use Symfony\Component\Console\Exception\ExceptionInterface;

/**
 * @internal
 */
final class CodeCoverageDriverNotAvailable extends InvalidArgumentException implements ExceptionInterface
{
    /**
     * Creates a new instance of test already exist.
     */
    public function __construct()
    {
        parent::__construct('No code coverage driver is available');
    }
}
