<?php

declare(strict_types=1);

use Pest\Datasets;
use Pest\PendingObjects\AfterEachCall;
use Pest\PendingObjects\BeforeEachCall;
use Pest\PendingObjects\TestCall;
use Pest\PendingObjects\UsesCall;
use Pest\Support\Backtrace;
use Pest\TestSuite;

/**
 * Runs the given `$closure` before all of the tests in current file.
 */
function beforeAll(Closure $closure): void
{
    TestSuite::getInstance()->beforeAll->set($closure);
}

/**
 * Runs the given `$closure` before each tests in current file.
 */
function beforeEach(Closure $closure = null): BeforeEachCall
{
    $filename = Backtrace::file();

    return new BeforeEachCall(TestSuite::getInstance(), $filename, $closure);
}

/**
 * Registers the given `$dataset` on Pest.
 *
 * @usage
 * ```
 * dataset('emails', ['enunomaduro@gmail.com', 'freek@spatie.be']);
 * ```
 *
 * @param string           $name    The name of the dataset
 * @param Closure|iterable $dataset
 */
function dataset(string $name, $dataset): void
{
    Datasets::set($name, $dataset);
}

/**
 * Registers the given `$class` as base test in the given directory.
 *
 * @usage
 * ```
 * // Uses a class in the current file.
 * uses(Tests\TestCase::class);
 *
 * // Uses a trait in the current file.
 * uses(Tests\TestCase::class);
 *
 * // Uses a class and a trait in a specific dir.
 * uses(Tests\TestCase::class)->in('Feature');
 *
 * // Uses a class and a trait in the current dir.
 * uses([Tests\TestCase::class, RefreshDatabase::class])->in(__DIR__);
 * ```
 *
 * @param array<int, string> ...$classAndTraits
 */
function uses(...$classAndTraits): UsesCall
{
    $filename = Backtrace::file();

    return new UsesCall($filename, $classAndTraits);
}

/**
 * Creates a new test.
 *
 * @usage
 * ```
 * test('foo', function () {
 *     assertTrue(true);
 * });
 * ```
 */
function test(string $description, Closure $closure = null): TestCall
{
    $filename = Backtrace::file();

    return new TestCall(TestSuite::getInstance(), $filename, $description, $closure);
}

/**
 * @return TestCall
 */
function it(string $description, Closure $closure = null)
{
    $filename = Backtrace::file();

    return new TestCall(TestSuite::getInstance(), $filename, sprintf('it %s', $description), $closure);
}

function afterEach(Closure $closure = null): AfterEachCall
{
    $filename = Backtrace::file();

    return new AfterEachCall(TestSuite::getInstance(), $filename, $closure);
}

/**
 * Runs the given `$closure` after all of the tests in current file.
 */
function afterAll(Closure $closure = null): void
{
    TestSuite::getInstance()->afterAll->set($closure);
}
