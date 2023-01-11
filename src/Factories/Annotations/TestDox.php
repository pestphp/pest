<?php

declare(strict_types=1);

namespace Pest\Factories\Annotations;

use Pest\Contracts\AddsAnnotations;
use Pest\Factories\TestCaseMethodFactory;

final class TestDox implements AddsAnnotations
{
    /**
     * {@inheritdoc}
     */
    public function __invoke(TestCaseMethodFactory $method, array $annotations): array
    {
        $annotations[] = "@testdox $method->description";

        return $annotations;
    }
}
