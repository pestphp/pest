<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use InvalidArgumentException;

/**
 * @internal
 */
final class TestCaseClassOrTraitNotFound extends InvalidArgumentException
{
    /**
     * Creates a new instance of after each already exist exception.
     */
    public function __construct(string $testCaseClass)
    {
        parent::__construct(sprintf('The class `%s` was not found.', $testCaseClass));
    }
}
