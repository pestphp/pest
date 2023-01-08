<?php

declare(strict_types=1);

namespace Pest\Factories\Annotations;

use Pest\Factories\TestCaseMethodFactory;

final class TestDox implements AddsAnnotation
{
    /**
     * Add metadata via test dox for TeamCity
     *
     * @param  array<int, string>  $annotations
     * @return array<int, string>
     */
    public function __invoke(TestCaseMethodFactory $method, array $annotations): array
    {
        // First test dox on class overrides the method name.
        $annotations[] = "@testdox $method->description";

        return $annotations;
    }
}
