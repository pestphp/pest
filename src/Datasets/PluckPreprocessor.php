<?php

declare(strict_types=1);

namespace Pest\Datasets;

use InvalidArgumentException;
use Pest\Contracts\Dataset\Preprocessor;

/**
 * @internal
 */
final class PluckPreprocessor implements Preprocessor
{
    public function getKey(): string
    {
        return 'pluck';
    }

    public function process(array $dataset, mixed $value): array
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('The pluck preprocessor should be given a string.');
        }

        /* @phpstan-ignore-next-line */
        return array_map(fn (array|object $values) => is_array($values) ? $values[$value] : $values->{$value}, $dataset);
    }
}
