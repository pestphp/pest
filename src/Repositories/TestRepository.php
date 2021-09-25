<?php

declare(strict_types=1);

namespace Pest\Repositories;

use Closure;
use Pest\Exceptions\DatasetMissing;
use Pest\Exceptions\ShouldNotHappen;
use Pest\Exceptions\TestAlreadyExist;
use Pest\Exceptions\TestCaseAlreadyInUse;
use Pest\Exceptions\TestCaseClassOrTraitNotFound;
use Pest\Factories\TestCaseFactory;
use Pest\Plugins\Environment;
use Pest\Support\Reflection;
use Pest\Support\Str;
use Pest\TestSuite;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class TestRepository
{
    /**
     * @var non-empty-string
     */
    private const SEPARATOR = '>>>';

    /**
     * @var array<string, TestCaseFactory>
     */
    private $state = [];

    /**
     * @var array<string, array<int, array<int, string|Closure>>>
     */
    private $uses = [];

    /**
     * Counts the number of test cases.
     */
    public function count(): int
    {
        return count($this->state);
    }

    /**
     * Returns the filename of each test that should be executed in the suite.
     *
     * @return array<int, string>
     */
    public function getFilenames(): array
    {
        $testsWithOnly = $this->testsUsingOnly();

        return array_values(array_map(function (TestCaseFactory $factory): string {
            return $factory->filename;
        }, count($testsWithOnly) > 0 ? $testsWithOnly : $this->state));
    }

    /**
     * Calls the given callable foreach test case.
     */
    public function build(TestSuite $testSuite, callable $each): void
    {
        $startsWith = function (string $target, string $directory): bool {
            return Str::startsWith($target, $directory . DIRECTORY_SEPARATOR);
        };

        foreach ($this->uses as $path => $uses) {
            [$classOrTraits, $groups, $hooks] = $uses;

            $setClassName = function (TestCaseFactory $testCase, string $key) use ($path, $classOrTraits, $groups, $startsWith, $hooks): void {
                [$filename] = explode(self::SEPARATOR, $key);

                if ((!is_dir($path) && $filename === $path) || (is_dir($path) && $startsWith($filename, $path))) {
                    foreach ($classOrTraits as $class) { /** @var string $class */
                        if (class_exists($class)) {
                            if ($testCase->class !== TestCase::class) {
                                throw new TestCaseAlreadyInUse($testCase->class, $class, $filename);
                            }
                            $testCase->class = $class;
                        } elseif (trait_exists($class)) {
                            $testCase->traits[] = $class;
                        }
                    }

                    // IDEA: Consider set the real lines on these.
                    $testCase->factoryProxies->add($filename, 0, 'addGroups', [$groups]);
                    $testCase->factoryProxies->add($filename, 0, 'addBeforeAll', [$hooks[0] ?? null]);
                    $testCase->factoryProxies->add($filename, 0, 'addBeforeEach', [$hooks[1] ?? null]);
                    $testCase->factoryProxies->add($filename, 0, 'addAfterEach', [$hooks[2] ?? null]);
                    $testCase->factoryProxies->add($filename, 0, 'addAfterAll', [$hooks[3] ?? null]);
                }
            };

            foreach ($this->state as $key => $test) {
                $setClassName($test, $key);
            }
        }

        $onlyState = $this->testsUsingOnly();

        $state = count($onlyState) > 0 ? $onlyState : $this->state;

        foreach ($state as $testFactory) {
            /** @var TestCaseFactory $testFactory */
            $tests = $testFactory->build($testSuite);
            foreach ($tests as $test) {
                $each($test);
            }
        }
    }

    /**
     * Return all tests that have called the only method.
     *
     * @return array<TestCaseFactory>
     */
    private function testsUsingOnly(): array
    {
        if (Environment::name() === Environment::CI) {
            return [];
        }

        return array_filter($this->state, function ($testFactory): bool {
            return $testFactory->only;
        });
    }

    /**
     * Uses the given `$testCaseClass` on the given `$paths`.
     *
     * @param array<int, string>  $classOrTraits
     * @param array<int, string>  $groups
     * @param array<int, string>  $paths
     * @param array<int, Closure> $hooks
     */
    public function use(array $classOrTraits, array $groups, array $paths, array $hooks): void
    {
        foreach ($classOrTraits as $classOrTrait) {
            if (!class_exists($classOrTrait) && !trait_exists($classOrTrait)) {
                throw new TestCaseClassOrTraitNotFound($classOrTrait);
            }
        }

        foreach ($paths as $path) {
            if (array_key_exists($path, $this->uses)) {
                $this->uses[$path] = [
                    array_merge($this->uses[$path][0], $classOrTraits),
                    array_merge($this->uses[$path][1], $groups),
                    $this->uses[$path][2] + $hooks, // NOTE: array_merge will destroy numeric indices
                ];
            } else {
                $this->uses[$path] = [$classOrTraits, $groups, $hooks];
            }
        }
    }

    /**
     * Sets a test case by the given filename and description.
     */
    public function set(TestCaseFactory $test): void
    {
        if ($test->description === null) {
            throw ShouldNotHappen::fromMessage('Trying to create a test without description.');
        }

        if (array_key_exists(sprintf('%s%s%s', $test->filename, self::SEPARATOR, $test->description), $this->state)) {
            throw new TestAlreadyExist($test->filename, $test->description);
        }

        if (!$test->receivesArguments()) {
            $arguments = Reflection::getFunctionArguments($test->test);

            if (count($arguments) > 0) {
                throw new DatasetMissing($test->filename, $test->description, $arguments);
            }
        }

        $this->state[sprintf('%s%s%s', $test->filename, self::SEPARATOR, $test->description)] = $test;
    }
}
