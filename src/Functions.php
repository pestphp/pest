<?php

declare(strict_types=1);

use Pest\Datasets;
use Pest\PendingObjects\AfterEachCall;
use Pest\PendingObjects\BeforeEachCall;
use Pest\PendingObjects\TestCall;
use Pest\PendingObjects\UsesCall;
use Pest\Support\Backtrace;
use Pest\Support\HigherOrderTapProxy;
use Pest\TestSuite;
use PHPUnit\Framework\TestCase;

/**
 * Runs the given closure before all tests in the current file.
 */
function beforeAll(Closure $closure): void
{
    TestSuite::getInstance()->beforeAll->set($closure);
}

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

/**
 * Registers the given dataset.
 *
 * @param Closure|iterable<int|string, mixed> $dataset
 */
function dataset(string $name, $dataset): void
{
    Datasets::set($name, $dataset);
}

/**
 * The uses function binds the given
 * arguments to test closures.
 */
function uses(string ...$classAndTraits): UsesCall
{
    $filename = Backtrace::file();

    return new UsesCall($filename, $classAndTraits);
}

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

/**
 * Shorthand function to skip the current test.
 *
 * @return TestCall|TestCase|mixed
 */
function xtest(string $description, Closure $closure = null): TestCall
{
    return test($description, $closure)->skip();
}

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

/**
 * Shorthand function to skip the current test.
 *
 * @return TestCall|TestCase|mixed
 */
function xit(string $description, Closure $closure = null): TestCall
{
    return it($description, $closure)->skip();
}

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

/**
 * Runs the given closure after all tests in the current file.
 */
function afterAll(Closure $closure): void
{
    TestSuite::getInstance()->afterAll->set($closure);
}
