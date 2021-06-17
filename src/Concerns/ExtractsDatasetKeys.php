<?php

declare(strict_types=1);

namespace Pest\Concerns;

use Pest\Exceptions\DatasetDoesNotExist;

const DATASET_KEY_EXTRACTION_PATTERN = '/^(.*):(.*)/';

trait ExtractsDatasetKeys
{
    /**
     * @return array[]
     */
    private static function extractDataset(string $datasetName): array
    {
        $realDatasetName = self::retrieveDatasetName($datasetName);

        $keys = self::retrieveDatasetKeys($datasetName);

        if (!array_key_exists($realDatasetName, self::$datasets)) {
            throw new DatasetDoesNotExist($datasetName);
        }

        $dataset = self::computeDataset(self::$datasets[$realDatasetName]);

        if (count($keys) === 0) {
            return $dataset;
        }

        return self::extractDatasetFromKeys($dataset, $keys);
    }

    /**
     * @return string[]
     */
    private static function retrieveDatasetKeys(string $datasetName): array
    {
        /* @phpstan-ignore-next-line */
        if (!preg_match(DATASET_KEY_EXTRACTION_PATTERN, $datasetName, $matches)) {
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
        if (!preg_match(DATASET_KEY_EXTRACTION_PATTERN, $datasetName, $matches)) {
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
    private static function extractDatasetFromKeys(array $dataset, array $keys): array
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
