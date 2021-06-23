<?php

declare(strict_types=1);

namespace Pest\Concerns;

use Pest\Exceptions\DatasetDoesNotExist;

/**
 * Implements some methods useful to extract specific keys from a registered dataset.
 *
 * given a dataset: dataset('people',  'Nuno'   => [
 *      'Nuno' => ['name' => 'Nuno', 'country' => 'Portugal', 'role' => 'Owner'],
 *      'Fabio' => ['name' => 'Fabio', 'country' => 'Italy', 'role' => 'Member'],
 *      'Oliver' => ['name' => 'Oliver', 'country' => 'Denmark', 'role' => 'Maintainer'],
 *  ],
 *
 * Keys are selected by using a specific pattern when calling a dataset for a
 * specific test, eg. ->with('people:name,age')
 */
trait ExtractsDatasetKeys
{
    /**
     * @internal
     *
     * @var string
     */
    private static $DATASET_KEY_EXTRACTION_PATTERN = '/^(.*):(.*)/';

    /**
     * @return array[]
     */
    private static function extractDataset(string $registeredDatasetName): array
    {
        $realDatasetName = self::retrieveDatasetName($registeredDatasetName);

        $keys = self::retrieveDatasetKeys($registeredDatasetName);

        if (!array_key_exists($realDatasetName, self::$datasets)) {
            throw new DatasetDoesNotExist($registeredDatasetName);
        }

        $dataset = self::computeDataset(self::$datasets[$realDatasetName]);

        if (count($keys) === 0) {
            return $dataset;
        }

        return self::extractDatasetKeys($dataset, $keys);
    }

    /**
     * @return string[]
     */
    private static function retrieveDatasetKeys(string $datasetName): array
    {
        /* @phpstan-ignore-next-line */
        if (!preg_match(self::$DATASET_KEY_EXTRACTION_PATTERN, $datasetName, $matches)) {
            throw new DatasetDoesNotExist($datasetName);
        }

        /** @var string[] $possibleKeys */
        $possibleKeys = explode(',', $matches[2] ?? '');

        $keys = [];
        foreach ($possibleKeys as $key) {
            $key = trim($key);

            if ($key === '') {
                continue;
            }

            $keys[] = $key;
        }

        return $keys;
    }

    private static function retrieveDatasetName(string $datasetName): string
    {
        /* @phpstan-ignore-next-line */
        if (!preg_match(self::$DATASET_KEY_EXTRACTION_PATTERN, $datasetName, $matches)) {
            throw new DatasetDoesNotExist($datasetName);
        }

        return trim($matches[1]);
    }

    /**
     * @param array[]  $dataset
     * @param string[] $keys
     *
     * @return array[]
     */
    private static function extractDatasetKeys(array $dataset, array $keys): array
    {
        $extractedDataset = [];

        foreach ($dataset as $datasetKey => $datasetArray) {
            foreach ($keys as $key) {
                $extractedDataset[$datasetKey][$key] = $datasetArray[$key] ?? null;
            }
        }

        return $extractedDataset;
    }
}
