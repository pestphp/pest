<?php

declare(strict_types=1);

namespace Pest;

use Closure;
use Pest\Datasets\PreprocessorRepository;
use Pest\Repositories\DatasetsRepository;
use ReflectionException;
use ReflectionFunction;
use ReflectionParameter;
use Traversable;

final class TestDataset
{
    private PreprocessorRepository $preprocessorRepository;

    /**
     * @param Closure|iterable<int|string, mixed>|string $dataset
     * @param array<int|string, mixed>                   $parameters
     */
    public function __construct(
        private Closure|iterable|string $dataset,
        private array $parameters,
    ) {
        $this->preprocessorRepository = new PreprocessorRepository();
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
        $dataset = $this->resolveFromString($this->dataset);
        $dataset = $this->resolveFromCallable($dataset);
        $dataset = $this->resolveFromTraversable($dataset);

        return $this->preprocess($dataset);
    }

    /**
     * @param Closure|iterable<int|string, mixed>|string $dataset
     *
     * @return Closure|iterable<int|string, mixed>
     */
    private function resolveFromString(Closure|iterable|string $dataset): Closure|iterable
    {
        if (!is_string($dataset)) {
            return $dataset;
        }

        return DatasetsRepository::getGlobalDataset($dataset);
    }

    /**
     * @param Closure|iterable<int|string, mixed> $dataset
     *
     * @return iterable<int|string, mixed>
     */
    private function resolveFromCallable(Closure|iterable $dataset): iterable
    {
        if (!is_callable($dataset)) {
            return $dataset;
        }

        /** @var iterable<int|string, mixed> $dataset */
        $dataset = call_user_func_array($dataset, $this->getActualParameters($dataset));

        return $dataset;
    }

    /**
     * @param iterable<int|string, mixed> $dataset
     *
     * @return array<int|string, mixed>
     */
    private function resolveFromTraversable(iterable $dataset): array
    {
        if (!($dataset instanceof Traversable)) {
            return $dataset;
        }

        return iterator_to_array($dataset);
    }

    /**
     * @param Closure|iterable<int|string, mixed> $dataset
     *
     * @return array<int|string, mixed>
     */
    private function getActualParameters(Closure|iterable $dataset): array
    {
        if (!is_callable($dataset)) {
            return [];
        }

        //@phpstan-ignore-next-line
        $reflection        = new ReflectionFunction($dataset);
        $datasetParameters = array_map(function (ReflectionParameter $reflectionParameter) {
            return $reflectionParameter->getName();
        }, $reflection->getParameters());

        return array_filter($this->parameters, function ($key) use ($datasetParameters): bool {
            if (!$this->preprocessorRepository->has($key)) {
                return true;
            }

            if (in_array($key, $datasetParameters, true)) {
                return true;
            }

            return false;
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * @return array<int|string, mixed>
     *
     * @throws ReflectionException
     */
    private function getPreprocessParameters(): array
    {
        $dataset = $this->resolveFromString($this->dataset);
        if (is_callable($dataset)) {
            //@phpstan-ignore-next-line
            $reflection        = new ReflectionFunction($dataset);
            $datasetParameters = array_map(function (ReflectionParameter $reflectionParameter) {
                return $reflectionParameter->getName();
            }, $reflection->getParameters());
        } else {
            $datasetParameters = [];
        }

        return array_filter($this->parameters, function ($key) use ($datasetParameters): bool {
            if (in_array($key, $datasetParameters, true)) {
                return false;
            }

            if (!$this->preprocessorRepository->has($key)) {
                return false;
            }

            return true;
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param array<int|string, mixed> $dataset
     *
     * @return array<int|string, mixed>
     *
     * @throws ReflectionException
     */
    private function preprocess(array $dataset): array
    {
        foreach ($this->getPreprocessParameters() as $functionName => $parameter) {
            $dataset = $this->preprocessorRepository
                ->get($functionName)
                ->process($dataset, $parameter);
        }

        return $dataset;
    }
}
