<?php

declare(strict_types=1);

namespace Pest;

use Pest\Repositories\AfterAllRepository;
use Pest\Repositories\AfterEachRepository;
use Pest\Repositories\BeforeAllRepository;
use Pest\Repositories\BeforeEachRepository;
use Pest\Repositories\TestRepository;

/**
 * @internal
 */
final class TestSuite
{
    /**
     * Holds the tests repository.
     */
    public TestRepository $tests;

    /**
     * Wether should show the coverage or not.
     */
    public bool $coverage = false;

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
    private static TestSuite $instance;

    /**
     * Creates a new instance of the test suite.
     */
    public function __construct(string $rootPath)
    {
        $this->beforeAll  = new BeforeAllRepository();
        $this->beforeEach = new BeforeEachRepository();
        $this->tests      = new TestRepository();
        $this->afterEach  = new AfterEachRepository();
        $this->afterAll   = new AfterAllRepository();

        $this->rootPath = $rootPath;
    }

    /**
     * Returns the current instance of the test suite.
     */
    public static function getInstance(string $rootPath = null): TestSuite
    {
        if (is_string($rootPath)) {
            return self::$instance ?? self::$instance = new TestSuite($rootPath);
        }

        return self::$instance;
    }
}
