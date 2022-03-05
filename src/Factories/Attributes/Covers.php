<?php

declare(strict_types=1);

namespace Pest\Factories\Attributes;

use Pest\Factories\Covers\CoversClass;
use Pest\Factories\Covers\CoversFunction;
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
            if ($covering instanceof CoversClass) {
                $attributes[] = "#[\PHPUnit\Framework\Attributes\CoversClass({$covering->class}]";

                if (!is_null($covering->method)) {
                    $attributes[] = "#[\PHPUnit\Framework\Attributes\CoversFunction({$covering->method}]";
                }
            } else if ($covering instanceof CoversFunction) {
                $attributes[] = "#[\PHPUnit\Framework\Attributes\CoversFunction({$covering->function}]";
            } else {
                $attributes[] = "#[\PHPUnit\Framework\Attributes\CoversNothing]";
            }
        }

        return $attributes;
    }
}
