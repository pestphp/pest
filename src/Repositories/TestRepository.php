<?php

declare(strict_types=1);

namespace Pest\Repositories;

use Pest\Exceptions\TestAlreadyExist;
use Pest\Exceptions\TestCaseAlreadyInUse;
use Pest\Exceptions\TestCaseClassOrTraitNotFound;
use Pest\Factories\TestCaseFactory;
use Pest\Support\Str;
use Pest\TestSuite;

/**
 * @internal
 */
final class TestRepository
{
    /**
     * @var array<string, TestCaseFactory>
     */
    private $state = [];

    /**
     * @var array<string, array<int, string>>
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
     * Calls the given callable foreach test case.
     */
    public function build(TestSuite $testSuite, callable $each): void
    {
        $startsWith = function (string $target, string $directory): bool {
            return Str::startsWith($target, $directory . DIRECTORY_SEPARATOR);
        };

        foreach ($this->uses as $path => $classOrTraits) {
            $setClassName = function (TestCaseFactory $testCase, string $key) use ($path, $classOrTraits, $startsWith): void {
                [$filename] = explode('@', $key);

                if ((!is_dir($path) && $filename === $path) || (is_dir($path) && $startsWith($filename, $path))) {
                    foreach ($classOrTraits as $class) {
                        if (class_exists($class)) {
                            if ($testCase->class !== \PHPUnit\Framework\TestCase::class) {
                                throw new TestCaseAlreadyInUse($testCase->class, $class, $filename);
                            }

                            $testCase->class = $class;
                        } elseif (trait_exists($class)) {
                            $testCase->traits[] = $class;
                        }
                    }
                }
            };

            foreach ($this->state as $key => $test) {
                $setClassName($test, $key);
            }
        }

        foreach ($this->state as $testFactory) {
            /* @var TestCaseFactory $testFactory */
            $tests = $testFactory->build($testSuite);
            foreach ($tests as $test) {
                $each($test);
            }
        }
    }

    /**
     * Uses the given `$testCaseClass` on the given `$paths`.
     *
     * @param array<int, string> $classOrTraits
     * @param array<int, string> $paths
     */
    public function use(array $classOrTraits, array $paths): void
    {
        foreach ($classOrTraits as $classOrTrait) {
            if (!class_exists($classOrTrait) && !trait_exists($classOrTrait)) {
                throw new TestCaseClassOrTraitNotFound($classOrTrait);
            }

            foreach ($paths as $path) {
                if (!array_key_exists($path, $this->uses)) {
                    $this->uses[$path] = [];
                }

                $this->uses[$path][] = $classOrTrait;
            }
        }
    }

    /**
     * Sets a test case by the given filename and description.
     */
    public function set(TestCaseFactory $test): void
    {
        if (array_key_exists(sprintf('%s@%s', $test->filename, $test->description), $this->state)) {
            throw new TestAlreadyExist($test->filename, $test->description);
        }

        $this->state[sprintf('%s@%s', $test->filename, $test->description)] = $test;
    }
}
