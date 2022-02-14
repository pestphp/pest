<?php

declare(strict_types=1);

namespace Pest\Datasets;

use InvalidArgumentException;
use Pest\Contracts\Dataset\Preprocessor;

/**
 * @internal
 */
final class OnlyPreprocessor implements Preprocessor
{
    public function getKey(): string
    {
        return 'only';
    }

    public function process(array $dataset, mixed $value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException('The only preprocessor should be given a string or array.');
        }

        return array_filter(
            $dataset,
            fn ($key) => in_array($key, $value, true),
            ARRAY_FILTER_USE_KEY
        );
    }
}
