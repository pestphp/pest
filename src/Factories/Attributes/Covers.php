<?php

declare(strict_types=1);

namespace Pest\Factories\Attributes;

use Pest\Factories\TestCaseMethodFactory;

/**
 * @internal
 */
final class Covers
{
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
            if (is_array($covering)) {
                $attributes[] = "#[\PHPUnit\Framework\Attributes\CoversClass({$covering[0]}]";
                $attributes[] = "#[\PHPUnit\Framework\Attributes\CoversFunction({$covering[1]}]";
            } else {
                $attributes[] = "#[\PHPUnit\Framework\Attributes\CoversClass($covering)]";
            }
        }

        return $attributes;
    }
}
