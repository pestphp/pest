<?php

declare(strict_types=1);

namespace Pest\Contracts\Dataset;

use InvalidArgumentException;

interface Preprocessor
{
    /**
     * The key that will be used to identify the named parameter in the dataset.
     */
    public function getKey(): string;

    /**
     * Process the given dataset using the provided parameter value and return
     * the filtered dataset.
     *
     * @param array<int|string, mixed> $dataset
     *
     * @return array<int|string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function process(array $dataset, mixed $value): array;
}
