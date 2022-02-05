<?php

declare(strict_types=1);

namespace Pest\Repositories;

use Closure;
use Pest\Exceptions\DatasetAlreadyExist;
use Pest\Exceptions\DatasetDoesNotExist;
use Pest\Exceptions\ShouldNotHappen;
use SebastianBergmann\Exporter\Exporter;
use function sprintf;
use Traversable;

/**
 * @internal
 */
final class DatasetsRepository
{
    /**
     * Holds the datasets.
     *
     * @var array<string, Closure|iterable<int|string, mixed>>
     */
    private static array $datasets = [];

    /**
     * Holds the withs.
     *
     * @var array<string, array<string, Closure|iterable<int|string, mixed>|string>>
     */
    private static array $withs = [];

    /**
     * Hold the withs' parameters.
     *
     * @var array<string, array<array<int|string, mixed>>>
     */
    private static array $withsParameters = [];

    /**
     * Sets the given.
     *
     * @param Closure|iterable<int|string, mixed> $data
     */
    public static function set(string $name, Closure|iterable $data): void
    {
        if (array_key_exists($name, self::$datasets)) {
            throw new DatasetAlreadyExist($name);
        }

        self::$datasets[$name] = $data;
    }

    /**
     * Sets the given "with".
     *
     * @param array<Closure|iterable<int|string, mixed>|string> $with
     * @param array<array<int|string, mixed>>                   $parameters
     */
    public static function with(string $filename, string $description, array $with, array $parameters): void
    {
        self::$withs[$filename . '>>>' . $description]           = $with;
        self::$withsParameters[$filename . '>>>' . $description] = $parameters;
    }

    /**
     * @return Closure|iterable<int|string, mixed>|never
     *
     * @throws ShouldNotHappen
     */
    public static function get(string $filename, string $description): Closure|iterable
    {
        $datasets           = self::$withs[$filename . '>>>' . $description];
        $datasetParameters  = self::$withsParameters[$filename . '>>>' . $description];

        $dataset = self::resolve($description, $datasets, $datasetParameters);

        if ($dataset === null) {
            throw ShouldNotHappen::fromMessage('Dataset [%s] not resolvable.');
        }

        return $dataset;
    }

    /**
     * Resolves the current dataset to an array value.
     *
     * @param array<Closure|iterable<int|string, mixed>|string> $dataset
     * @param array<array<int|string, mixed>>                   $datasetParameters
     *
     * @return array<string, mixed>|null
     */
    public static function resolve(string $description, array $dataset, array $datasetParameters = []): array|null
    {
        /* @phpstan-ignore-next-line */
        if (empty($dataset)) {
            return null;
        }

        $dataset = self::processDatasets($dataset, $datasetParameters);

        $datasetCombinations = self::getDatasetsCombinations($dataset);

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
     * @param array<Closure|iterable<int|string, mixed>|string> $datasets
     * @param array<array<int|string, mixed>>                   $datasetParameters
     *
     * @return array<array<mixed>>
     */
    private static function processDatasets(array $datasets, array $datasetParameters): array
    {
        $processedDatasets = [];

        foreach ($datasets as $index => $data) {
            $processedDataset = [];

            if (is_string($data)) {
                if (!array_key_exists($data, self::$datasets)) {
                    throw new DatasetDoesNotExist($data);
                }

                $datasets[$index] = self::$datasets[$data];
            }

            if (is_callable($datasets[$index])) {
                $datasets[$index] = call_user_func_array($datasets[$index], $datasetParameters[$index] ?? []);
            }

            if ($datasets[$index] instanceof Traversable) {
                $datasets[$index] = iterator_to_array($datasets[$index]);
            }

            //@phpstan-ignore-next-line
            foreach ($datasets[$index] as $key => $values) {
                $values             = is_array($values) ? $values : [$values];
                $processedDataset[] = [
                    'label'  => self::getDatasetDescription($key, $values), //@phpstan-ignore-line
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
