<?php

declare(strict_types=1);

namespace Pest\Repositories;

use Closure;
use Pest\Dataset;
use Pest\Exceptions\ShouldNotHappen;
use SebastianBergmann\Exporter\Exporter;
use function sprintf;

/**
 * @internal
 */
final class DatasetsRepository
{
    /**
     * Holds the withs.
     *
     * @var array<string, array<string, Dataset>>
     */
    private static array $withs = [];

    /**
     * Sets the given "with".
     *
     * @param array<Dataset> $with
     */
    public static function with(string $filename, string $description, array $with): void
    {
        self::$withs[$filename . '>>>' . $description] = $with;
    }

    /**
     * @return Closure|iterable<int|string, mixed>|never
     *
     * @throws ShouldNotHappen
     */
    public static function get(string $filename, string $description): Closure|iterable
    {
        $datasets = self::$withs[$filename . '>>>' . $description];

        $dataset = self::resolve($description, $datasets);

        if ($dataset === null) {
            throw ShouldNotHappen::fromMessage('Dataset [%s] not resolvable.');
        }

        return $dataset;
    }

    /**
     * Resolves the current dataset to an array value.
     *
     * @param array<Dataset> $datasets
     *
     * @return array<string, mixed>|null
     */
    public static function resolve(string $description, array $datasets): array|null
    {
        /* @phpstan-ignore-next-line */
        if (empty($datasets)) {
            return null;
        }

        $datasets = self::processDatasets($datasets);

        $datasetCombinations = self::getDatasetsCombinations($datasets);

        $datasetDescriptions = [];
        $datasetValues       = [];

        foreach ($datasetCombinations as $datasetCombination) {
            $partialDescriptions = [];
            $values              = [];

            foreach ($datasetCombination as $datasetCombinationElement) {
                $partialDescriptions[] = $datasetCombinationElement['label'];

                // @phpstan-ignore-next-line
                $values = array_merge($values, $datasetCombinationElement['values']);
            }

            $datasetDescriptions[] = $description . ' with ' . implode(' / ', $partialDescriptions);
            $datasetValues[]       = $values;
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
     * @param array<Dataset> $datasets
     *
     * @return array<array<mixed>>
     */
    private static function processDatasets(array $datasets): array
    {
        $processedDatasets = [];

        foreach ($datasets as $dataset) {
            $processedDataset = [];

            foreach ($dataset->resolve() as $key => $values) {
                $values             = is_array($values) ? $values : [$values];
                $processedDataset[] = [
                    'label'  => self::getDatasetDescription($key, $values),
                    'values' => $values,
                ];
            }

            $processedDatasets[] = $processedDataset;
        }

        return $processedDatasets;
    }

    /**
     * @param array<array<mixed>> $combinations
     *
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

        //@phpstan-ignore-next-line
        return $result;
    }

    /**
     * @param array<int, mixed> $data
     */
    private static function getDatasetDescription(int|string $key, array $data): string
    {
        $exporter = new Exporter();

        if (is_int($key)) {
            return sprintf('(%s)', $exporter->shortenedRecursiveExport($data));
        }

        return sprintf('data set "%s"', $key);
    }
}
