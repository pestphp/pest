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
     * @param Traversable<int|string, mixed>|Closure|iterable<int|string, mixed>|string|null $data
     *
     * @return array<string, mixed>
     */
    public static function resolve(string $description, $data): array
    {
        /* @phpstan-ignore-next-line */
        if (is_null($data) || empty($data)) {
            return [$description => []];
        }

        if (is_string($data)) {
            $data = self::get($data);
        }

        if (is_callable($data)) {
            $data = call_user_func($data);
        }

        if ($data instanceof Traversable) {
            $data = iterator_to_array($data);
        }

        $dataSetDescriptions = [];
        $dataSetValues       = [];

        foreach ($data as $key => $values) {
            $values = is_array($values) ? $values : [$values];

            $dataSetDescriptions[] = $description . self::getDataSetDescription($key, $values);
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
     * @param int|string        $key
     * @param array<int, mixed> $data
     */
    private static function getDataSetDescription($key, array $data): string
    {
        $exporter = new Exporter();

        $nameInsert = is_string($key) ? \sprintf('data set "%s" ', $key) : '';

        return \sprintf(' with %s(%s)', $nameInsert, $exporter->shortenedRecursiveExport($data));
    }
}
