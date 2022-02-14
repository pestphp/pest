<?php

declare(strict_types=1);

namespace Pest\Datasets;

use InvalidArgumentException;
use Pest\Contracts\Dataset\Preprocessor;

/**
 * @internal
 */
final class MapPreprocessor implements Preprocessor
{
    public function getKey(): string
    {
        return 'map';
    }

    public function process(array $dataset, mixed $value): array
    {
        if (!is_callable($value)) {
            throw new InvalidArgumentException('The map preprocessor should be given a callable.');
        }

        return array_map($value, $dataset);
    }
}
