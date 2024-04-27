<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use Exception;

final class DatasetArgumentsMismatch extends Exception
{
    public function __construct(int $requiredCount, int $suppliedCount)
    {
        if ($requiredCount <= $suppliedCount) {
            parent::__construct('Test argument names and dataset keys do not match');
        } else {
            parent::__construct(sprintf('Test expects %d arguments but dataset only provides %d', $requiredCount, $suppliedCount));
        }
    }

    //
}
