<?php

declare(strict_types=1);

use Pest\Concerns\Expectable;
use Pest\Configuration;
use Pest\Exceptions\AfterAllWithinDescribe;
use Pest\Exceptions\BeforeAllWithinDescribe;
use Pest\Expectation;
use Pest\Mutate\Contracts\MutationTestRunner;
use Pest\Mutate\Repositories\ConfigurationRepository;
use Pest\PendingCalls\AfterEachCall;
use Pest\PendingCalls\BeforeEachCall;
use Pest\PendingCalls\DescribeCall;
use Pest\PendingCalls\TestCall;
use Pest\PendingCalls\UsesCall;
use Pest\Repositories\DatasetsRepository;
use Pest\Support\Backtrace;
use Pest\Support\Container;
use Pest\Support\DatasetInfo;
use Pest\Support\HigherOrderTapProxy;
use Pest\TestSuite;
use PHPUnit\Framework\TestCase;

if (! function_exists('expect')) {
    /**
     * Creates a new expectation.
     *
     * @template TValue
     *
     * @param  TValue|null  $value
     * @return Expectation<TValue|null>
     */
    function expect(mixed $value = null): Expectation
    {
        return new Expectation($value);
    }
}

if (! function_exists('beforeAll')) {
    /**
     * Runs the given closure before all tests in the current file.
     */
    function beforeAll(Closure $closure): void
    {
        if (DescribeCall::describing() !== []) {
            $filename = Backtrace::file();

            throw new BeforeAllWithinDescribe($filename);
        }

        TestSuite::getInstance()->beforeAll->set($closure);
    }
}

if (! function_exists('beforeEach')) {
    /**
     * Runs the given closure before each test in the current file.
     *
     * @param-closure-this TestCase  $closure
     *
     * @return HigherOrderTapProxy<Expectable|TestCall|TestCase>|Expectable|TestCall|TestCase|mixed
     */
    function beforeEach(?Closure $closure = null): BeforeEachCall
    {
        $filename = Backtrace::file();

        return new BeforeEachCall(TestSuite::getInstance(), $filename, $closure);
    }
}

if (! function_exists('dataset')) {
    /**
     * Registers the given dataset.
     *
     * @param  Closure|iterable<int|string, mixed>  $dataset
     */
    function dataset(string $name, Closure|iterable $dataset): void
    {
        $scope = DatasetInfo::scope(Backtrace::datasetsFile());

        DatasetsRepository::set($name, $dataset, $scope);
    }
}

if (! function_exists('describe')) {
    /**
     * Adds the given closure as a group of tests. The first argument
     * is the group description; the second argument is a closure
     * that contains the group tests.
     *
     * @return HigherOrderTapProxy<Expectable|TestCall|TestCase>|Expectable|TestCall|TestCase|mixed
     */
    function describe(string $description, Closure $tests): DescribeCall
    {
        $filename = Backtrace::testFile();

        return new DescribeCall(TestSuite::getInstance(), $filename, $description, $tests);
    }
}

if (! function_exists('uses')) {
    /**
     * The uses function binds the given
     * arguments to test closures.
     *
     * @param  class-string  ...$classAndTraits
     */
    function uses(string ...$classAndTraits): UsesCall
    {
        $filename = Backtrace::file();

        return new UsesCall($filename, array_values($classAndTraits));
    }
}

if (! function_exists('pest')) {
    /**
     * Creates a new Pest configuration instance.
     */
    function pest(): Configuration
    {
        return new Configuration(Backtrace::file());
    }
}

if (! function_exists('test')) {
    /**
     * Adds the given closure as a test. The first argument
     * is the test description; the second argument is
     * a closure that contains the test expectations.
     *
     * @param-closure-this TestCase  $closure
     *
     * @return Expectable|TestCall|TestCase|mixed
     */
    function test(?string $description = null, ?Closure $closure = null): HigherOrderTapProxy|TestCall
    {
        if ($description === null && TestSuite::getInstance()->test instanceof \PHPUnit\Framework\TestCase) {
            return new HigherOrderTapProxy(TestSuite::getInstance()->test);
        }

        $filename = Backtrace::testFile();

        return new TestCall(TestSuite::getInstance(), $filename, $description, $closure);
    }
}

if (! function_exists('it')) {
    /**
     * Adds the given closure as a test. The first argument
     * is the test description; the second argument is
     * a closure that contains the test expectations.
     *
     * @param-closure-this TestCase  $closure
     *
     * @return Expectable|TestCall|TestCase|mixed
     */
    function it(string $description, ?Closure $closure = null): TestCall
    {
        $description = sprintf('it %s', $description);

        /** @var TestCall $test */
        $test = test($description, $closure);

        return $test;
    }
}

if (! function_exists('todo')) {
    /**
     * Creates a new test that is marked as "todo".
     *
     * @return Expectable|TestCall|TestCase|mixed
     */
    function todo(string $description): TestCall
    {
        $test = test($description);

        assert($test instanceof TestCall);

        return $test->todo();
    }
}

if (! function_exists('afterEach')) {
    /**
     * Runs the given closure after each test in the current file.
     *
     * @param-closure-this TestCase  $closure
     *
     * @return Expectable|HigherOrderTapProxy<Expectable|TestCall|TestCase>|TestCall|mixed
     */
    function afterEach(?Closure $closure = null): AfterEachCall
    {
        $filename = Backtrace::file();

        return new AfterEachCall(TestSuite::getInstance(), $filename, $closure);
    }
}

if (! function_exists('afterAll')) {
    /**
     * Runs the given closure after all tests in the current file.
     */
    function afterAll(Closure $closure): void
    {
        if (DescribeCall::describing() !== []) {
            $filename = Backtrace::file();

            throw new AfterAllWithinDescribe($filename);
        }

        TestSuite::getInstance()->afterAll->set($closure);
    }
}

if (! function_exists('covers')) {
    /**
     * Specifies which classes, or functions, a test case covers.
     *
     * @param  array<int, string>|string  $classesOrFunctions
     */
    function covers(array|string ...$classesOrFunctions): void
    {
        $filename = Backtrace::file();

        $beforeEachCall = (new BeforeEachCall(TestSuite::getInstance(), $filename));

        $beforeEachCall->covers(...$classesOrFunctions);
        $beforeEachCall->group('__pest_mutate_only');

        /** @var MutationTestRunner $runner */
        $runner = Container::getInstance()->get(MutationTestRunner::class);
        /** @var \Pest\Mutate\Repositories\ConfigurationRepository $configurationRepository */
        $configurationRepository = Container::getInstance()->get(ConfigurationRepository::class);
        $everything = $configurationRepository->cliConfiguration->toArray()['everything'] ?? false;
        $classes = $configurationRepository->cliConfiguration->toArray()['classes'] ?? false;
        $paths = $configurationRepository->cliConfiguration->toArray()['paths'] ?? false;

        if ($runner->isEnabled() && ! $everything && ! is_array($classes) && ! is_array($paths)) {
            $beforeEachCall->only('__pest_mutate_only');
        }
    }
}

if (! function_exists('mutates')) {
    /**
     * Specifies which classes, enums, or traits a test case mutates.
     *
     * @param  array<int, string>|string  $targets
     */
    function mutates(array|string ...$targets): void
    {
        $filename = Backtrace::file();

        $beforeEachCall = (new BeforeEachCall(TestSuite::getInstance(), $filename));
        $beforeEachCall->group('__pest_mutate_only');

        /** @var MutationTestRunner $runner */
        $runner = Container::getInstance()->get(MutationTestRunner::class);
        /** @var \Pest\Mutate\Repositories\ConfigurationRepository $configurationRepository */
        $configurationRepository = Container::getInstance()->get(ConfigurationRepository::class);
        $everything = $configurationRepository->cliConfiguration->toArray()['everything'] ?? false;
        $classes = $configurationRepository->cliConfiguration->toArray()['classes'] ?? false;
        $paths = $configurationRepository->cliConfiguration->toArray()['paths'] ?? false;

        if ($runner->isEnabled() && ! $everything && ! is_array($classes) && ! is_array($paths)) {
            $beforeEachCall->only('__pest_mutate_only');
        }

        /** @var ConfigurationRepository $configurationRepository */
        $configurationRepository = Container::getInstance()->get(ConfigurationRepository::class);
        $paths = $configurationRepository->cliConfiguration->toArray()['paths'] ?? false;

        if (! is_array($paths)) {
            $configurationRepository->globalConfiguration('default')->class(...$targets); // @phpstan-ignore-line
        }
    }
}
