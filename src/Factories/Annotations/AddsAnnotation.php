<?php

declare(strict_types=1);

namespace Pest\Factories\Annotations;

use Pest\Factories\TestCaseMethodFactory;

/**
 * @interal
 */
interface AddsAnnotation
{
    /**
     * Adds annotations to method
     *
     * @param  array<int, string>  $annotations
     * @return array<int, string>
     */
    public function __invoke(TestCaseMethodFactory $method, array $annotations): array;
}
