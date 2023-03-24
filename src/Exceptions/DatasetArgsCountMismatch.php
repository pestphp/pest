<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use Exception;

final class DatasetArgsCountMismatch extends Exception
{
    public function __construct(int $requiredCount, int $suppliedCount)
    {
        parent::__construct(sprintf('Test expects %d arguments but dataset only provides %d', $requiredCount, $suppliedCount));
    }
}
