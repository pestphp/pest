<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use InvalidArgumentException;

/**
 * @internal
 */
final class DatasetDoesNotExist extends InvalidArgumentException
{
    /**
     * Creates a new instance of dataset does not exist.
     */
    public function __construct(string $name)
    {
        parent::__construct(sprintf("A dataset with the name `%s` does not exist. You can create it using `dataset('name', ['a', 'b']);`.", $name));
    }
}
