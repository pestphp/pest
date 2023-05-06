<?php

namespace Pest\Factories\Annotations;

use Pest\Contracts\AddsAnnotations;
use Pest\Factories\Testbench\Environment as EnvironmentFactory;
use Pest\Factories\TestCaseMethodFactory;

final class Environment implements AddsAnnotations
{
    public function __invoke(TestCaseMethodFactory $method, array $annotations): array
    {
        if(($method->environment[0] ?? null) instanceof EnvironmentFactory) {
            $annotations[] = "@define-env {$method->environment[0]->name}";
        }

        return $annotations;
    }
}
