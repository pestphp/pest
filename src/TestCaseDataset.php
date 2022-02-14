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

final class TestCaseDataset
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
        $this->resolveFromString();
        $this->resolveFromCallable();
        $this->resolveFromTraversable();
        $this->preprocess();

        //@phpstan-ignore-next-line
        return $this->dataset;
    }

    private function resolveFromString(): void
    {
        if (!is_string($this->dataset)) {
            return;
        }

        $this->dataset = DatasetsRepository::getGlobalDataset($this->dataset);
    }

    private function resolveFromCallable(): void
    {
        if (!is_callable($this->dataset)) {
            return;
        }

        /** @var iterable<int|string, mixed> $dataset */
        $dataset = call_user_func_array($this->dataset, $this->getActualParameters());

        $this->dataset = $dataset;
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

        //@phpstan-ignore-next-line
        $reflection        = new ReflectionFunction($this->dataset);
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
        if (is_callable($this->dataset)) {
            //@phpstan-ignore-next-line
            $reflection        = new ReflectionFunction($this->dataset);
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

    private function preprocess(): void
    {
        foreach ($this->getPreprocessParameters() as $functionName => $parameter) {
            /** @var array<int|string, mixed> $dataset */
            $dataset = $this->dataset;

            $dataset = $this->preprocessorRepository
                ->get($functionName)
                ->process($dataset, $parameter);

            $this->dataset = $dataset;
        }
    }
}
