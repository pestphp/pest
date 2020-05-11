<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use Exception;
use RuntimeException;

/**
 * @internal
 */
final class ShouldNotHappen extends RuntimeException
{
    /**
     * Creates a new instance of should not happen.
     */
    public function __construct(Exception $exception)
    {
        $message = $exception->getMessage();

        parent::__construct(sprintf(<<<EOF

This should not happen - please create an new issue here: https://github.com/pestphp/pest.

- Issue: %s
- PHP version: %s
- Operating system: %s
EOF
            , $message, phpversion(), PHP_OS), 1, $exception);
    }

    /**
     * Creates a new instance of should not happen without a specific exception.
     */
    public static function fromMessage(string $message): ShouldNotHappen
    {
        return new ShouldNotHappen(new Exception($message));
    }
}
