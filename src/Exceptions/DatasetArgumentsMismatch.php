<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use Exception;

final class DatasetArgumentsMismatch extends Exception
{
    public function __construct(int $requiredCount, int $suppliedCount)
    {
        if ($requiredCount <= $suppliedCount) {
            parent::__construct('The test arguments do not match the dataset keys. Please make sure each argument is named after a key in the dataset.');
        } else {
            parent::__construct(sprintf('The test expects [%d] argument(s), but the dataset only provides [%d].', $requiredCount, $suppliedCount));
        }
    }
}
