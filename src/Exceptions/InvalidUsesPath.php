<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use InvalidArgumentException;

/**
 * @internal
 */
final class InvalidUsesPath extends InvalidArgumentException
{
    /**
     * Creates a new instance of invalid uses path.
     */
    public function __construct(string $target)
    {
        parent::__construct(sprintf('The path `%s` is not valid.', $target));
    }
}
