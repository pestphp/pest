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
            // Split class and method name
            $class = null;
            if (str_contains($depend, '::')) {
                [$class, $depend] = explode('::', $depend);
            }

            $depend = Str::evaluable($method->describing !== null ? Str::describe($method->describing, $depend) : $depend);

            // Add class name to method name and add annotation
            if ($class !== null) {
                $depend = "$class::$depend";
            }
            $annotations[] = "@depends $depend";
        }

        return $annotations;
    }
}
