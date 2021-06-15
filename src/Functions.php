<?php

declare(strict_types=1);

use Pest\Datasets;
use Pest\Expectation;
use Pest\PendingObjects\AfterEachCall;
use Pest\PendingObjects\BeforeEachCall;
use Pest\PendingObjects\TestCall;
use Pest\PendingObjects\UsesCall;
use Pest\Support\Backtrace;
use Pest\Support\Extendable;
use Pest\Support\HigherOrderTapProxy;
use Pest\TestSuite;
use PHPUnit\Framework\TestCase;

/**
 * Creates a new expectation.
 *
 * @param mixed $value the Value
 *
 * @return Expectation|Extendable
 */
function expect($value = null)
{
    if (func_num_args() === 0) {
        return new Extendable(Expectation::class);
    }

    return new Expectation($value);
}

if (!function_exists('beforeAll')) {
    /**
     * Runs the given closure before all tests in the current file.
     */
    function beforeAll(Closure $closure): void
    {
        TestSuite::getInstance()->beforeAll->set($closure);
    }
}

if (!function_exists('beforeEach')) {
    /**
     * Runs the given closure before each test in the current file.
     *
     * @return BeforeEachCall|TestCase|mixed
     */
    function beforeEach(Closure $closure = null): BeforeEachCall
    {
        $filename = Backtrace::file();

        return new BeforeEachCall(TestSuite::getInstance(), $filename, $closure);
    }
}

if (!function_exists('dataset')) {
    /**
     * Registers the given dataset.
     *
     * @param Closure|iterable<int|string, mixed> $dataset
     */
    function dataset(string $name, $dataset): void
    {
        Datasets::set($name, $dataset);
    }
}

if (!function_exists('uses')) {
    /**
     * The uses function binds the given
     * arguments to test closures.
     */
    function uses(string ...$classAndTraits): UsesCall
    {
        $filename = Backtrace::file();

        return new UsesCall($filename, $classAndTraits);
    }
}

if (!function_exists('test')) {
    /**
     * Adds the given closure as a test. The first argument
     * is the test description; the second argument is
     * a closure that contains the test expectations.
     *
     * @return TestCall|TestCase|mixed
     */
    function test(string $description = null, Closure $closure = null)
    {
        if ($description === null && TestSuite::getInstance()->test !== null) {
            return new HigherOrderTapProxy(TestSuite::getInstance()->test);
        }

        $filename = Backtrace::testFile();

        return new TestCall(TestSuite::getInstance(), $filename, $description, $closure);
    }
}

if (!function_exists('it')) {
    /**
     * Adds the given closure as a test. The first argument
     * is the test description; the second argument is
     * a closure that contains the test expectations.
     *
     * @return TestCall|TestCase|mixed
     */
    function it(string $description, Closure $closure = null): TestCall
    {
        $description = sprintf('it %s', $description);

        return test($description, $closure);
    }
}

if (!function_exists('afterEach')) {
    /**
     * Runs the given closure after each test in the current file.
     *
     * @return AfterEachCall|TestCase|mixed
     */
    function afterEach(Closure $closure = null): AfterEachCall
    {
        $filename = Backtrace::file();

        return new AfterEachCall(TestSuite::getInstance(), $filename, $closure);
    }
}

if (!function_exists('afterAll')) {
    /**
     * Runs the given closure after all tests in the current file.
     */
    function afterAll(Closure $closure): void
    {
        TestSuite::getInstance()->afterAll->set($closure);
    }
}
