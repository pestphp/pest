<?php

declare(strict_types=1);

namespace Pest\Contracts;

interface TestCaseFilter
{
    /**
     * Whether the test case is accepted.
     */
    public function accept(string $testCaseFilename): bool;
}
