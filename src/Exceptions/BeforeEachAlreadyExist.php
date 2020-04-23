<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use InvalidArgumentException;

/**
 * @internal
 */
final class BeforeEachAlreadyExist extends InvalidArgumentException
{
    /**
     * Creates a new instance of before each already exist exception.
     */
    public function __construct(string $filename)
    {
        parent::__construct(sprintf('The beforeEach already exist in the filename `%s`.', $filename));
    }
}
