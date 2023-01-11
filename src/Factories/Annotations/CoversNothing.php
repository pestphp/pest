<?php

declare(strict_types=1);

namespace Pest\Factories\Annotations;

use Pest\Contracts\AddsAnnotations;
use Pest\Factories\Covers\CoversNothing as CoversNothingFactory;
use Pest\Factories\TestCaseMethodFactory;

/**
 * @internal
 */
final class CoversNothing implements AddsAnnotations
{
    /**
     * {@inheritdoc}
     */
    public function __invoke(TestCaseMethodFactory $method, array $annotations): array
    {
        if (($method->covers[0] ?? null) instanceof CoversNothingFactory) {
            $annotations[] = '@coversNothing';
        }

        return $annotations;
    }
}
