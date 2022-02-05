<?php

declare(strict_types=1);

namespace Pest;

use Closure;
use Pest\Exceptions\DatasetAlreadyExist;
use Pest\Exceptions\DatasetDoesNotExist;
use ReflectionFunction;
use ReflectionParameter;
use Traversable;

final class Dataset
{
    /**
     * Holds global datasets.
     *
     * @var array<string, Closure|iterable<int|string, mixed>>
     */
    private static array $globalDatasets = [];

    /**
     * @param Closure|iterable<int|string, mixed>|string $dataset
     * @param array<int|string, mixed>                   $parameters
     */
    public function __construct(
        private Closure|iterable|string $dataset,
        private array $parameters = [],
    ) {
    }

    /**
     * @return array<string, Closure>
     */
    private static function preprocessingFunctions(): array
    {
        return [
            'map'   => fn (array $dataset, Closure $mapCallback) => array_map($mapCallback, $dataset),
            'pluck' => fn (array $dataset, string $key) => array_map(fn (array $values) => $values[$key], $dataset),
            'only'  => function (array $dataset, string|array $allowedKeys) {
                if (!is_array($allowedKeys)) {
                    $allowedKeys = [$allowedKeys];
                }

                return array_filter($dataset, function ($key) use ($allowedKeys) {
                    return in_array($key, $allowedKeys);
                }, ARRAY_FILTER_USE_KEY);
            },
            'except' => function (array $dataset, string|array $allowedKeys) {
                if (!is_array($allowedKeys)) {
                    $allowedKeys = [$allowedKeys];
                }

                return array_filter($dataset, function ($key) use ($allowedKeys) {
                    return !in_array($key, $allowedKeys);
                }, ARRAY_FILTER_USE_KEY);
            },
        ];
    }

    /**
     * Sets a new global dataset.
     *
     * @param Closure|iterable<int|string, mixed> $data
     */
    public static function setGlobalDataset(string $name, Closure|iterable $data): void
    {
        if (array_key_exists($name, self::$globalDatasets)) {
            throw new DatasetAlreadyExist($name);
        }

        self::$globalDatasets[$name] = $data;
    }

    public function isNamedDataset(): bool
    {
        return is_string($this->dataset);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function resolve(): array
    {
        $this->resolveFromString();
        $this->resolveFromCallable();
        $this->resolveFromTraversable();
        $this->preprocess();

        return $this->dataset;
    }

    private function resolveFromString(): void
    {
        if (!is_string($this->dataset)) {
            return;
        }

        if (!array_key_exists($this->dataset, self::$globalDatasets)) {
            throw new DatasetDoesNotExist($this->dataset);
        }

        $this->dataset = self::$globalDatasets[$this->dataset];
    }

    private function resolveFromCallable(): void
    {
        if (!is_callable($this->dataset)) {
            return;
        }

        $this->dataset = call_user_func_array($this->dataset, $this->getActualParameters());
    }

    private function resolveFromTraversable(): void
    {
        if (!($this->dataset instanceof Traversable)) {
            return;
        }

        $this->dataset = iterator_to_array($this->dataset);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function getActualParameters(): array
    {
        if (!is_callable($this->dataset)) {
            return [];
        }

        $reflection        = new ReflectionFunction($this->dataset);
        $datasetParameters = array_map(function (ReflectionParameter $reflectionParameter) {
            return $reflectionParameter->getName();
        }, $reflection->getParameters());

        return array_filter($this->parameters, function ($key) use ($datasetParameters): bool {
            if (!array_key_exists($key, self::preprocessingFunctions())) {
                return true;
            }

            if (in_array($key, $datasetParameters)) {
                return true;
            }

            return false;
        }, ARRAY_FILTER_USE_KEY);
    }

    private function getPreprocessParameters(): array
    {
        if (is_callable($this->dataset)) {
            $reflection        = new ReflectionFunction($this->dataset);
            $datasetParameters = array_map(function (ReflectionParameter $reflectionParameter) {
                return $reflectionParameter->getName();
            }, $reflection->getParameters());
        } else {
            $datasetParameters = [];
        }

        return array_filter($this->parameters, function ($key) use ($datasetParameters): bool {
            if (in_array($key, $datasetParameters)) {
                return false;
            }

            if (!array_key_exists($key, self::preprocessingFunctions())) {
                return false;
            }

            return true;
        }, ARRAY_FILTER_USE_KEY);
    }

    private function preprocess(): void
    {
        foreach ($this->getPreprocessParameters() as $functionName => $parameter) {
            $this->dataset = call_user_func(self::preprocessingFunctions()[$functionName], $this->dataset, $parameter);
        }
    }
}
