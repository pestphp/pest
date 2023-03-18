<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use Exception;

final class DatasetArgsCountMismatch extends Exception
{
    public function __construct(string $dataName)
    {
        parent::__construct(sprintf('Number of arguments mismatch between test and dataset [%s]', $dataName));
    }
}
