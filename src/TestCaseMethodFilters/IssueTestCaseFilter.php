<?php

declare(strict_types=1);

namespace Pest\TestCaseMethodFilters;

use Pest\Contracts\TestCaseMethodFilter;
use Pest\Factories\TestCaseMethodFactory;

final readonly class IssueTestCaseFilter implements TestCaseMethodFilter
{
    public function __construct(private int $number)
    {
        //
    }

    public function accept(TestCaseMethodFactory $factory): bool
    {
        return in_array($this->number, $factory->issues, true);
    }
}
