<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use InvalidArgumentException;
use Symfony\Component\Console\Exception\ExceptionInterface;

/**
 * @internal
 */
final class TestCaseAlreadyInUse extends InvalidArgumentException implements ExceptionInterface
{
    /**
     * Creates a new instance of test case already in use.
     */
    public function __construct(string $inUse, string $newOne, string $folder)
    {
        parent::__construct(sprintf('Test case `%s` can not be used. The folder `%s` already uses the test case `%s`',
            $newOne, $folder, $inUse));
    }
}
