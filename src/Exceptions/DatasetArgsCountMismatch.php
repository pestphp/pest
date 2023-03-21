<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use Exception;

final class DatasetArgsCountMismatch extends Exception
{
    public function __construct(string $dataName, int $requiredCount, int $suppliedCount)
    {
        parent::__construct(sprintf('Test expects %d arguments but dataset [%s] only provides %d', $requiredCount, $dataName, $suppliedCount));
    }
}
