<?php

declare(strict_types=1);

use Pest\Datasets;
use Pest\PendingObjects\AfterEachCall;
use Pest\PendingObjects\BeforeEachCall;
use Pest\PendingObjects\TestCall;
use Pest\PendingObjects\UsesCall;
use Pest\Support\Backtrace;
use Pest\TestSuite;
use PHPUnit\Framework\TestCase;

/**
 * Runs the given closure after all tests in the current file.
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
 * @param Closure|iterable $dataset
 */
function dataset(string $name, $dataset): void
{
    Datasets::set($name, $dataset);
}

/**
 * The uses function adds the binds the
 * given arguments to test closures.
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
function test(string $description, Closure $closure = null): TestCall
{
    $filename = Backtrace::file();

    return new TestCall(TestSuite::getInstance(), $filename, $description, $closure);
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
    $filename = Backtrace::file();

    return new TestCall(TestSuite::getInstance(), $filename, sprintf('it %s', $description), $closure);
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
function afterAll(Closure $closure = null): void
{
    TestSuite::getInstance()->afterAll->set($closure);
}
