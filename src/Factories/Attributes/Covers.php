<?php

declare(strict_types=1);

namespace Pest\Factories\Attributes;

use Pest\Factories\Covers\CoversClass;
use Pest\Factories\Covers\CoversFunction;
use Pest\Factories\TestCaseMethodFactory;

/**
 * @internal
 */
final class Covers extends Attribute
{
    /**
     * Determine if the attribute should be placed above the classe instead of above the method.
     *
     * @var bool
     */
    public const ABOVE_CLASS = true;

    /**
     * Adds attributes regarding the "covers" feature.
     *
     * @param \Pest\Factories\TestCaseMethodFactory $method
     * @param array<int, string> $attributes
     *
     * @return array<int, string>
     */
    public function __invoke(TestCaseMethodFactory $method, array $attributes): array
    {
        foreach ($method->covers as $covering) {
            if ($covering instanceof CoversClass) {
                $attributes[] = "#[\PHPUnit\Framework\Attributes\CoversClass({$covering->class}::class)]";
            } else if ($covering instanceof CoversFunction) {
                $attributes[] = "#[\PHPUnit\Framework\Attributes\CoversFunction('{$covering->function}')]";
            } else {
                $attributes[] = "#[\PHPUnit\Framework\Attributes\CoversNothing]";
            }
        }

        return $attributes;
    }
}
