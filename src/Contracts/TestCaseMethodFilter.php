<?php

declare(strict_types=1);

namespace Pest\Contracts;

use Pest\Factories\TestCaseMethodFactory;

interface TestCaseMethodFilter
{
    /**
     * Whether the test case method is accepted.
     */
    public function accept(TestCaseMethodFactory $factory): bool;
}
