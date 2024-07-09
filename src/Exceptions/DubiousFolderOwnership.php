<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use RuntimeException;

/**
 * @internal
 */
final class DubiousFolderOwnership extends RuntimeException
{
    /**
     * Creates a new Exception instance.
     */
    public function __construct()
    {
        parent::__construct('Dubious folder ownership');
    }
}
