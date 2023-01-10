<?php

declare(strict_types=1);

namespace Pest\Factories\Attributes;

use Pest\Factories\TestCaseMethodFactory;

/**
 * @internal
 */
abstract class Attribute
{
    /**
     * Determine if the attribute should be placed above the class instead of above the method.
     */
    public static bool $above = false;

    /**
     * @param  array<int, string>  $attributes
     * @return array<int, string>
     */
    public function __invoke(TestCaseMethodFactory $method, array $attributes): array // @phpstan-ignore-line
    {
        return $attributes;
    }
}
