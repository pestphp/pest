<?php

declare(strict_types=1);

namespace Pest\TestCaseMethodFilters;

use Pest\Contracts\TestCaseMethodFilter;
use Pest\Factories\TestCaseMethodFactory;

final readonly class FlakyTestCaseFilter implements TestCaseMethodFilter
{
    public function accept(TestCaseMethodFactory $factory): bool
    {
        return $factory->flakyTries !== null;
    }
}
