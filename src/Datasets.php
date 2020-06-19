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
     * @var array<string, \Closure|iterable<int, mixed>>
     */
    private static $datasets = [];

    /**
     * Sets the given.
     *
     * @param Closure|iterable<int, mixed> $data
     */
    public static function set(string $name, $data): void
    {
        if (array_key_exists($name, self::$datasets)) {
            throw new DatasetAlreadyExist($name);
        }

        self::$datasets[$name] = $data;
    }

    /**
     * @return Closure|iterable<int, mixed>
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
     * @param Traversable<int, mixed>|Closure|iterable<int, mixed>|string|null $data
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

        $namedData = [];
        foreach ($data as $values) {
            $values = is_array($values) ? $values : [$values];

            $name             = $description . self::getDataSetDescription($values);
            $namedData[$name] = $values;
        }

        return $namedData;
    }

    /**
     * @param array<int, mixed> $data
     */
    private static function getDataSetDescription(array $data): string
    {
        $exporter = new Exporter();
        $formatted = [];

        foreach ($data as $value) {
            $formatted[] = $exporter->export($value);
        }

        return \sprintf(' with (%s)', implode(', ', $formatted));
    }
}
