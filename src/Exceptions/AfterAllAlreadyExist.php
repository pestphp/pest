<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use InvalidArgumentException;

/**
 * @internal
 */
final class AfterAllAlreadyExist extends InvalidArgumentException
{
    /**
     * Creates a new instance of after all already exist exception.
     */
    public function __construct(string $filename)
    {
        parent::__construct(sprintf('The afterAll already exist in the filename `%s`.', $filename));
    }
}
