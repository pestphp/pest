<?php

declare(strict_types=1);

namespace Pest;

use Pest\Exceptions\InvalidPestCommand;
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
     *
     * @var TestRepository
     */
    public $tests;

    /**
     * Whether should show the coverage or not.
     *
     * @var bool
     */
    public $coverage = false;

    /**
     * The minimum coverage.
     *
     * @var float
     */
    public $coverageMin = 0.0;

    /**
     * Holds the before each repository.
     *
     * @var BeforeEachRepository
     */
    public $beforeEach;

    /**
     * Holds the before all repository.
     *
     * @var BeforeAllRepository
     */
    public $beforeAll;

    /**
     * Holds the after each repository.
     *
     * @var AfterEachRepository
     */
    public $afterEach;

    /**
     * Holds the after all repository.
     *
     * @var AfterAllRepository
     */
    public $afterAll;

    /**
     * Holds the root path.
     *
     * @var string
     */
    public $rootPath;

    /**
     * Holds an instance of the test suite.
     *
     * @var TestSuite
     */
    private static $instance;

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

        if (self::$instance === null) {
            throw new InvalidPestCommand();
        }

        return self::$instance;
    }
}
