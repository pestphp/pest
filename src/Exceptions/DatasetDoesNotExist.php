<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use InvalidArgumentException;
use Symfony\Component\Console\Exception\ExceptionInterface;

/**
 * @internal
 */
final class DatasetDoesNotExist extends InvalidArgumentException implements ExceptionInterface
{
    /**
     * Creates a new instance of dataset does not exist.
     */
    public function __construct(string $name)
    {
        parent::__construct(sprintf("A dataset with the name `%s` does not exist. You can create it using `dataset('%s', ['a', 'b']);`.", $name, $name));
    }
}
