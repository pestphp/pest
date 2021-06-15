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
            return self::extract_dataset_keys($name);
        }

        return self::$datasets[$name];
    }

    public static function unset(string $name): void
    {
        if (array_key_exists($name, self::$datasets)) {
            unset(self::$datasets[$name]);
        }
    }

    /**
     * @return array[]
     */
    private static function extract_dataset_keys(string $dataset_name): array
    {
        $original_dataset_name = $dataset_name;

        $pieces = explode(':', $dataset_name);

        if ($pieces == false) {
            throw new DatasetDoesNotExist($original_dataset_name);
        }

        $dataset_name = trim($pieces[0]);

        if (!array_key_exists($dataset_name, self::$datasets)) {
            throw new DatasetDoesNotExist($original_dataset_name);
        }

        $dataset = self::computeDataset(self::$datasets[$dataset_name]);

        /** @var string[] $possibleKeys */
        $possibleKeys = explode(',', $pieces[1] ?? '');

        $keysToExtract = [];
        foreach ($possibleKeys as $key) {
            $key = trim($key);

            if ($key === '') {
                continue;
            }

            $keysToExtract[] = $key;
        }

        if (count($keysToExtract) === 0) {
            return $dataset;
        }

        $extracted_dataset = [];

        foreach ($dataset as $dataset_key => $dataset_array) {
            foreach ($keysToExtract as $key) {
                $extracted_dataset[$dataset_key][$key] = $dataset_array[$key] ?? null;
            }
        }

        return $extracted_dataset;
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

            $datasets[$index] = self::computeDataset($data);

            foreach ($datasets[$index] as $key => $values) {
                /* @phpstan-ignore-next-line */
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
     * @param Closure|iterable<int|string, mixed>|string $data
     *
     * @return array<array>
     */
    private static function computeDataset($data): array
    {
        if (is_string($data)) {
            $data = self::get($data);
        }

        if (is_callable($data)) {
            $data = call_user_func($data);
        }

        if ($data instanceof Traversable) {
            $data = iterator_to_array($data);
        }

        /* @var array $data */
        return $data;
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
