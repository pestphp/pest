<?php

declare(strict_types=1);

namespace Pest\Repositories;

use Closure;
use Pest\Exceptions\TestCaseAlreadyInUse;
use Pest\Exceptions\TestCaseClassOrTraitNotFound;
use Pest\Factories\TestCaseFactory;
use Pest\Factories\TestCaseMethodFactory;
use Pest\Support\Str;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class TestRepository
{
    /**
     * @var array<string, TestCaseFactory>
     */
    private array $testCases = [];

    /**
     * @var array<string, array{0: array<int, string>, 1: array<int, string>, 2: array<int, string|Closure>}>
     */
    private array $uses = [];

    /**
     * Counts the number of test cases.
     */
    public function count(): int
    {
        return count($this->testCases);
    }

    /**
     * Returns the filename of each test that should be executed in the suite.
     *
     * @return array<int, string>
     */
    public function getFilenames(): array
    {
        $testCases = array_filter($this->testCases, static fn (TestCaseFactory $testCase) => $testCase->methodsUsingOnly() !== []);

        if ($testCases === []) {
            $testCases = $this->testCases;
        }

        return array_values(array_map(static fn (TestCaseFactory $factory): string => $factory->filename, $testCases));
    }

    /**
     * Uses the given `$testCaseClass` on the given `$paths`.
     *
     * @param  array<int, string>  $classOrTraits
     * @param  array<int, string>  $groups
     * @param  array<int, string>  $paths
     * @param  array<int, Closure>  $hooks
     */
    public function use(array $classOrTraits, array $groups, array $paths, array $hooks): void
    {
        foreach ($classOrTraits as $classOrTrait) {
            if (class_exists($classOrTrait)) {
                continue;
            }
            if (trait_exists($classOrTrait)) {
                continue;
            }
            throw new TestCaseClassOrTraitNotFound($classOrTrait);
        }

        foreach ($paths as $path) {
            if (array_key_exists($path, $this->uses)) {
                $this->uses[$path] = [
                    [...$this->uses[$path][0], ...$classOrTraits],
                    [...$this->uses[$path][1], ...$groups],
                    $this->uses[$path][2] + $hooks, // NOTE: array_merge will destroy numeric indices
                ];
            } else {
                $this->uses[$path] = [$classOrTraits, $groups, $hooks];
            }
        }
    }

    public function get(string $filename): TestCaseFactory
    {
        return $this->testCases[$filename];
    }

    /**
     * Sets a new test case method.
     */
    public function set(TestCaseMethodFactory $method): void
    {
        if (! array_key_exists($method->filename, $this->testCases)) {
            $this->testCases[$method->filename] = new TestCaseFactory($method->filename);
        }

        $this->testCases[$method->filename]->addMethod($method);
    }

    /**
     * Makes a Test Case from the given filename, if exists.
     */
    public function makeIfNeeded(string $filename): void
    {
        if (array_key_exists($filename, $this->testCases)) {
            $this->make($this->testCases[$filename]);
        }
    }

    /**
     * Makes a Test Case using the given factory.
     */
    private function make(TestCaseFactory $testCase): void
    {
        $startsWith = static fn (string $target, string $directory): bool => Str::startsWith($target, $directory.DIRECTORY_SEPARATOR);

        foreach ($this->uses as $path => $uses) {
            [$classOrTraits, $groups, $hooks] = $uses;

            if ((! is_dir($path) && $testCase->filename === $path) || (is_dir($path) && $startsWith($testCase->filename, $path))) {
                foreach ($classOrTraits as $class) {
                    /** @var string $class */
                    if (class_exists($class)) {
                        if ($testCase->class !== TestCase::class) {
                            throw new TestCaseAlreadyInUse($testCase->class, $class, $testCase->filename);
                        }
                        $testCase->class = $class;
                    } elseif (trait_exists($class)) {
                        $testCase->traits[] = $class;
                    }
                }

                foreach ($testCase->methods as $method) {
                    foreach ($groups as $group) {
                        $method->groups[] = $group;
                    }
                }

                foreach ($testCase->methods as $method) {
                    $method->groups = [...$groups, ...$method->groups];
                }

                $testCase->factoryProxies->add($testCase->filename, 0, '__addBeforeAll', [$hooks[0] ?? null]);
                $testCase->factoryProxies->add($testCase->filename, 0, '__addBeforeEach', [$hooks[1] ?? null]);
                $testCase->factoryProxies->add($testCase->filename, 0, '__addAfterEach', [$hooks[2] ?? null]);
                $testCase->factoryProxies->add($testCase->filename, 0, '__addAfterAll', [$hooks[3] ?? null]);
            }
        }

        $testCase->make();
    }
}
