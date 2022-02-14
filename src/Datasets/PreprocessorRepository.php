<?php

declare(strict_types=1);

namespace Pest\Datasets;

use InvalidArgumentException;
use Pest\Contracts\Dataset\Preprocessor;

/**
 * @internal
 */
final class PreprocessorRepository
{
    /**
     * @var array<Preprocessor>
     */
    private array $preprocessors;

    public function __construct()
    {
        $this->preprocessors = [
            new ExceptPreprocessor(),
            new MapPreprocessor(),
            new OnlyPreprocessor(),
            new PluckPreprocessor(),
        ];
    }

    public function has(string|int $key): bool
    {
        return count($this->itemsForKey($key)) > 0;
    }

    public function get(string|int $key): Preprocessor
    {
        if (!$this->has($key)) {
            throw new InvalidArgumentException("[$key] is not a supported preprocessor.");
        }

        return $this->itemsForKey($key)[0];
    }

    /**
     * @return array<Preprocessor>
     */
    private function itemsForKey(string|int $key): array
    {
        return array_values(array_filter(
            $this->preprocessors,
            fn (Preprocessor $preprocessor) => $preprocessor->getKey() === $key
        ));
    }
}
