<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use InvalidArgumentException;

/**
 * @internal
 */
final class DatasetAlreadyExist extends InvalidArgumentException
{
    /**
     * Creates a new instance of dataset already exist.
     */
    public function __construct(string $name)
    {
        parent::__construct(sprintf('A dataset with the name `%s` already exist.', $name));
    }
}
