<?php

declare(strict_types=1);

namespace Pest;

use Pest\Exceptions\InvalidPestCommand;
use Pest\Repositories\AfterAllRepository;
use Pest\Repositories\AfterEachRepository;
use Pest\Repositories\BeforeAllRepository;
use Pest\Repositories\BeforeEachRepository;
use Pest\Repositories\TestRepository;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class TestSuite
{
    /**
     * Holds the current test case.
     */
    public ?TestCase $test = null;

    /**
     * Holds the tests repository.
     */
    public TestRepository $tests;

    /**
     * Holds the before each repository.
     */
    public BeforeEachRepository $beforeEach;

    /**
     * Holds the before all repository.
     */
    public BeforeAllRepository $beforeAll;

    /**
     * Holds the after each repository.
     */
    public AfterEachRepository $afterEach;

    /**
     * Holds the after all repository.
     */
    public AfterAllRepository $afterAll;

    /**
     * Holds the root path.
     */
    public string $rootPath;

    /**
     * Holds an instance of the test suite.
     */
    private static ?TestSuite $instance = null;

    /**
     * Creates a new instance of the test suite.
     */
    public function __construct(
        string $rootPath,
        public string $testPath,
    ) {
        $this->beforeAll = new BeforeAllRepository();
        $this->beforeEach = new BeforeEachRepository();
        $this->tests = new TestRepository();
        $this->afterEach = new AfterEachRepository();
        $this->afterAll = new AfterAllRepository();

        $this->rootPath = (string) realpath($rootPath);
    }

    /**
     * Returns the current instance of the test suite.
     */
    public static function getInstance(
        string $rootPath = null,
        string $testPath = null,
    ): TestSuite {
        if (is_string($rootPath) && is_string($testPath)) {
            self::$instance = new TestSuite($rootPath, $testPath);

            foreach (Plugin::$callables as $callable) {
                $callable();
            }

            return self::$instance;
        }

        if (! self::$instance instanceof self) {
            Panic::with(new InvalidPestCommand());
        }

        return self::$instance;
    }
}
