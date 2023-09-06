<?php

declare(strict_types=1);

namespace Pest\Factories\Annotations;

use Pest\Contracts\AddsAnnotations;
use Pest\Factories\TestCaseMethodFactory;
use Pest\Support\Str;

/**
 * @internal
 */
final class Depends implements AddsAnnotations
{
    /**
     * {@inheritdoc}
     */
    public function __invoke(TestCaseMethodFactory $method, array $annotations): array
    {
        foreach ($method->depends as $depend) {
            $depend = Str::evaluable($method->describing !== null ? Str::describe($method->describing, $depend) : $depend);

            $annotations[] = "@depends $depend";
        }

        return $annotations;
    }
}
