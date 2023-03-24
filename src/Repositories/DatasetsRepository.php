<?php

declare(strict_types=1);

namespace Pest\Repositories;

use Closure;
use Generator;
use Pest\Exceptions\DatasetAlreadyExists;
use Pest\Exceptions\DatasetDoesNotExist;
use Pest\Exceptions\ShouldNotHappen;
use Pest\Support\Exporter;
use function sprintf;
use Traversable;

/**
 * @internal
 */
final class DatasetsRepository
{
    private const SEPARATOR = '>>';

    /**
     * Holds the datasets.
     *
     * @var array<string, Closure|iterable<int|string, mixed>>
     */
    private static array $datasets = [];

    /**
     * Holds the withs.
     *
     * @var array<array<string, Closure|iterable<int|string, mixed>|string>>
     */
    private static array $withs = [];

    /**
     * Sets the given.
     *
     * @param  Closure|iterable<int|string, mixed>  $data
     */
    public static function set(string $name, Closure|iterable $data, string $scope): void
    {
        $datasetKey = "$scope".self::SEPARATOR."$name";

        if (array_key_exists("$datasetKey", self::$datasets)) {
            throw new DatasetAlreadyExists($name, $scope);
        }

        self::$datasets[$datasetKey] = $data;
    }

    /**
     * Sets the given "with".
     *
     * @param  array<Closure|iterable<int|string, mixed>|string>  $with
     */
    public static function with(string $filename, string $description, array $with): void
    {
        self::$withs["$filename".self::SEPARATOR."$description"] = $with;
    }

    public static function has(string $filename, string $description): bool
    {
        return array_key_exists($filename.self::SEPARATOR.$description, self::$withs);
    }

    /**
     * @return Closure|array<int|string, mixed>|never
     *
     * @throws ShouldNotHappen
     */
    public static function get(string $filename, string $description)
    {
        $dataset = self::$withs[$filename.self::SEPARATOR.$description];

        $dataset = self::resolve($dataset, $filename);

        if ($dataset === null) {
            throw ShouldNotHappen::fromMessage('Dataset [%s] not resolvable.');
        }

        return $dataset;
    }

    /**
     * Resolves the current dataset to an array value.
     *
     * @param  array<Closure|iterable<int|string, mixed>|string>  $dataset
     * @return array<string, mixed>|null
     */
    public static function resolve(array $dataset, string $currentTestFile): array|null
    {
        if ($dataset === []) {
            return null;
        }

        $dataset = self::processDatasets($dataset, $currentTestFile);

        $datasetCombinations = self::getDatasetsCombinations($dataset);

        $datasetDescriptions = [];
        $datasetValues = [];

        foreach ($datasetCombinations as $datasetCombination) {
            $partialDescriptions = [];
            $values = [];

            foreach ($datasetCombination as $datasetCombinationElement) {
                $partialDescriptions[] = $datasetCombinationElement['label'];

                // @phpstan-ignore-next-line
                $values = array_merge($values, $datasetCombinationElement['values']);
            }

            $datasetDescriptions[] = implode(' / ', $partialDescriptions);
            $datasetValues[] = $values;
        }

        foreach (array_count_values($datasetDescriptions) as $descriptionToCheck => $count) {
            if ($count > 1) {
                $index = 1;
                foreach ($datasetDescriptions as $i => $datasetDescription) {
                    if ($datasetDescription === $descriptionToCheck) {
                        $datasetDescriptions[$i] .= sprintf(' #%d', $index++);
                    }
                }
            }
        }

        $namedData = [];
        foreach ($datasetDescriptions as $i => $datasetDescription) {
            $namedData[$datasetDescription] = $datasetValues[$i];
        }

        return $namedData;
    }

    /**
     * @param  array<Closure|iterable<int|string, mixed>|string>  $datasets
     * @return array<array<mixed>>
     */
    private static function processDatasets(array $datasets, string $currentTestFile): array
    {
        $processedDatasets = [];

        foreach ($datasets as $index => $data) {
            $processedDataset = [];

            if (is_string($data)) {
                $datasets[$index] = self::getScopedDataset($data, $currentTestFile);
            }

            if (is_callable($datasets[$index])) {
                $datasets[$index] = call_user_func($datasets[$index]);
            }

            if ($datasets[$index] instanceof Traversable) {
                $preserveKeysForArrayIterator = $datasets[$index] instanceof Generator
                    && is_string($datasets[$index]->key());

                $datasets[$index] = iterator_to_array($datasets[$index], $preserveKeysForArrayIterator);
            }

            foreach ($datasets[$index] as $key => $values) {
                $values = is_array($values) ? $values : [$values];
                $processedDataset[] = [
                    'label' => self::getDatasetDescription($key, $values),
                    'values' => $values,
                ];
            }

            $processedDatasets[] = $processedDataset;
        }

        return $processedDatasets;
    }

    /**
     * @return Closure|iterable<int|string, mixed>
     */
    private static function getScopedDataset(string $name, string $currentTestFile): Closure|iterable
    {
        $matchingDatasets = array_filter(self::$datasets, function (string $key) use ($name, $currentTestFile): bool {
            [$datasetScope, $datasetName] = explode(self::SEPARATOR, $key);

            if ($name !== $datasetName) {
                return false;
            }

            return str_starts_with($currentTestFile, $datasetScope);
        }, ARRAY_FILTER_USE_KEY);

        $closestScopeDatasetKey = array_reduce(
            array_keys($matchingDatasets),
            fn ($keyA, $keyB) => $keyA !== null && strlen((string) $keyA) > strlen($keyB) ? $keyA : $keyB
        );

        if ($closestScopeDatasetKey === null) {
            throw new DatasetDoesNotExist($name);
        }

        return $matchingDatasets[$closestScopeDatasetKey];
    }

    /**
     * @param  array<array<mixed>>  $combinations
     * @return array<array<array<mixed>>>
     */
    private static function getDatasetsCombinations(array $combinations): array
    {
        $result = [[]];
        foreach ($combinations as $index => $values) {
            $tmp = [];
            foreach ($result as $resultItem) {
                foreach ($values as $value) {
                    $tmp[] = array_merge($resultItem, [$index => $value]);
                }
            }
            $result = $tmp;
        }

        // @phpstan-ignore-next-line
        return $result;
    }

    /**
     * @param  array<int, mixed>  $data
     */
    private static function getDatasetDescription(int|string $key, array $data): string
    {
        $exporter = Exporter::default();

        if (is_int($key)) {
            return sprintf('(%s)', $exporter->shortenedRecursiveExport($data));
        }

        return sprintf('dataset "%s"', $key);
    }
}
