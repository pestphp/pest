<?php

declare(strict_types=1);

namespace Pest\TestCaseMethodFilters;

use Pest\Contracts\TestCaseMethodFilter;
use Pest\Factories\TestCaseMethodFactory;

final readonly class NotesTestCaseFilter implements TestCaseMethodFilter
{
    /**
     * Filter the test case methods.
     */
    public function accept(TestCaseMethodFactory $factory): bool
    {
        return $factory->notes !== [];
    }
}
