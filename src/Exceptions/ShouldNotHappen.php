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
    public function __construct(Exception $exception)
    {
        $message = $exception->getMessage();

        parent::__construct(sprintf(<<<'EOF'
This should not have happened. Please report it here: https://github.com/pestphp/pest/issues

  Issue: %s
  PHP version: %s
  Operating system: %s
EOF
            , $message, phpversion(), PHP_OS), 1, $exception);
    }

    public static function fromMessage(string $message): ShouldNotHappen
    {
        return new ShouldNotHappen(new Exception($message));
    }
}
