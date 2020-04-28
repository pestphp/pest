<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use InvalidArgumentException;
use Symfony\Component\Console\Exception\ExceptionInterface;

/**
 * @internal
 */
final class TestAlreadyExist extends InvalidArgumentException implements ExceptionInterface
{
    /**
     * Creates a new instance of test already exist.
     */
    public function __construct(string $fileName, string $description)
    {
        parent::__construct(sprintf('A test with the description `%s` already exist in the filename `%s`.', $description, $fileName));
    }
}
