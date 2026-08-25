<?php

declare(strict_types=1);

namespace Pest\TestCaseMethodFilters;

use Pest\Contracts\TestCaseMethodFilter;
use Pest\Factories\TestCaseMethodFactory;

final readonly class AssigneeTestCaseFilter implements TestCaseMethodFilter
{
    public function __construct(private string $assignee)
    {
        //
    }

    public function accept(TestCaseMethodFactory $factory): bool
    {
        return array_filter($factory->assignees, fn (string $assignee): bool => str_starts_with($assignee, $this->assignee)) !== [];
    }
}
