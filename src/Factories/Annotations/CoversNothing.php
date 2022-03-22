<?php

declare(strict_types=1);

namespace Pest\Factories\Annotations;

use Pest\Factories\Covers\CoversNothing as CoversNothingFactory;
use Pest\Factories\TestCaseMethodFactory;

/**
 * @internal
 */
final class CoversNothing
{
    /**
     * Adds annotations regarding the "depends" feature.
     *
     * @param array<int, string> $annotations
     *
     * @return array<int, string>
     */
    public function __invoke(TestCaseMethodFactory $method, array $annotations): array
    {
        if (($method->covers[0] ?? null) instanceof CoversNothingFactory) {
            $annotations[] = '@coversNothing';
        }

        return $annotations;
    }
}
