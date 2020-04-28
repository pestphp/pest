<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use InvalidArgumentException;
use Symfony\Component\Console\Exception\ExceptionInterface;

/**
 * @internal
 */
final class FileNotFound extends InvalidArgumentException implements ExceptionInterface
{
    /**
     * Creates a new instance of file not found.
     */
    public function __construct(string $filename)
    {
        parent::__construct(sprintf('The file or folder with the name `%s` not found.', $filename));
    }
}
