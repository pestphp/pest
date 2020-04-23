<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use InvalidArgumentException;

/**
 * @internal
 */
final class AfterEachAlreadyExist extends InvalidArgumentException
{
    /**
     * Creates a new instance of after each already exist exception.
     */
    public function __construct(string $filename)
    {
        parent::__construct(sprintf('The afterEach already exist in the filename `%s`.', $filename));
    }
}
