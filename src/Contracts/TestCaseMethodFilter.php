<?php

declare(strict_types=1);

namespace Pest\Contracts;

use Pest\Factories\TestCaseMethodFactory;

interface TestCaseMethodFilter
{
    public function accept(TestCaseMethodFactory $factory): bool;
}
