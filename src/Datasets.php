<?php

declare(strict_types=1);

namespace Pest;

use Closure;
use Pest\Exceptions\DatasetAlreadyExist;
use Pest\Exceptions\DatasetDoesNotExist;
use SebastianBergmann\Exporter\Exporter;
use Traversable;

/**
 * @internal
 */
final class Datasets
{
    /**
     * Holds the datasets.
     *
     * @var array<int|string, Closure|iterable<int|string, mixed>>
     */
    private static $datasets = [];

    /**
     * Sets the given.
     *
     * @param Closure|iterable<int|string, mixed> $data
     */
    public static function set(string $name, $data): void
    {
        if (array_key_exists($name, self::$datasets)) {
            throw new DatasetAlreadyExist($name);
        }

        self::$datasets[$name] = $data;
    }

    /**
     * @return Closure|iterable<int|string, mixed>
     */
    public static function get(string $name)
    {
        if (!array_key_exists($name, self::$datasets)) {
            throw new DatasetDoesNotExist($name);
        }

        return self::$datasets[$name];
    }

    /**
     * Resolves the current dataset to an array value.
     *
     * @param array<Closure|iterable<int|string, mixed>|string> $datasets
     *
     * @return array<string, mixed>
     */
    public static function resolve(string $description, array $datasets): array
    {
        /* @phpstan-ignore-next-line */
        if (empty($datasets)) {
            return [$description => []];
        }

        $datasets = self::processDatasets($datasets);

        $datasetCombinations = self::getDataSetsCombinations($datasets);

        $dataSetDescriptions = [];
        $dataSetValues       = [];

        foreach ($datasetCombinations as $datasetCombination) {
            $partialDescriptions = [];
            $values              = [];

            foreach ($datasetCombination as $dataset_data) {
                $partialDescriptions[] = $dataset_data['label'];
                $values                = array_merge($values, $dataset_data['values']);
            }

            $dataSetDescriptions[] = $description . ' with ' . implode(' / ', $partialDescriptions);
            $dataSetValues[]       = $values;
        }

        foreach (array_count_values($dataSetDescriptions) as $descriptionToCheck => $count) {
            if ($count > 1) {
                $index = 1;
                foreach ($dataSetDescriptions as $i => $dataSetDescription) {
                    if ($dataSetDescription === $descriptionToCheck) {
                        $dataSetDescriptions[$i] .= sprintf(' #%d', $index++);
                    }
                }
            }
        }

        $namedData = [];
        foreach ($dataSetDescriptions as $i => $dataSetDescription) {
            $namedData[$dataSetDescription] = $dataSetValues[$i];
        }

        return $namedData;
    }

    /**
     * @param array<Closure|iterable<int|string, mixed>|string> $datasets
     *
     * @return array<array>
     */
    private static function processDatasets(array $datasets): array
    {
        $processedDatasets = [];

        foreach ($datasets as $index => $data) {
            $processedDataset = [];

            if (is_string($data)) {
                $datasets[$index] = self::get($data);
            }

            if (is_callable($datasets[$index])) {
                $datasets[$index] = call_user_func($datasets[$index]);
            }

            if ($datasets[$index] instanceof Traversable) {
                $datasets[$index] = iterator_to_array($datasets[$index]);
            }

            foreach ($datasets[$index] as $key => $values) {
                $values             = is_array($values) ? $values : [$values];
                $processedDataset[] = [
                    'label'  => self::getDataSetDescription($key, $values),
                    'values' => $values,
                ];
            }

            $processedDatasets[] = $processedDataset;
        }

        return $processedDatasets;
    }

    /**
     * @param array<array> $combinations
     *
     * @return array<array>
     */
    private static function getDataSetsCombinations(array $combinations): array
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

        return $result;
    }

    /**
     * @param int|string        $key
     * @param array<int, mixed> $data
     */
    private static function getDataSetDescription($key, array $data): string
    {
        $exporter = new Exporter();

        if (is_int($key)) {
            return \sprintf('(%s)', $exporter->shortenedRecursiveExport($data));
        }

        return \sprintf('data set "%s"', $key);
    }
}
