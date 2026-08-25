<?php

declare(strict_types=1);

namespace Pest\Contracts;

interface TestCaseFilter
{
    public function accept(string $testCaseFilename): bool;
}
