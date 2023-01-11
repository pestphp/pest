<?php

declare(strict_types=1);

namespace Pest\Contracts;

use Pest\Factories\TestCaseMethodFactory;

/**
 * @interal
 */
interface AddsAnnotations
{
    /**
     * Adds annotations to the given test case method.
     *
     * @param  array<int, string>  $annotations
     * @return array<int, string>
     */
    public function __invoke(TestCaseMethodFactory $method, array $annotations): array;
}
