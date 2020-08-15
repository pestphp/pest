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
     *
     * @var TestCase|null
     */
    public $test;

    /**
     * Holds the tests repository.
     *
     * @var TestRepository
     */
    public $tests;

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

        $this->rootPath = (string) realpath($rootPath);
    }

    /**
     * Returns the current instance of the test suite.
     */
    public static function getInstance(string $rootPath = null): TestSuite
    {
        if (is_string($rootPath)) {
            self::$instance = new TestSuite($rootPath);

            foreach (Plugin::$callables as $callable) {
                $callable();
            }

            return self::$instance;
        }

        if (self::$instance === null) {
            throw new InvalidPestCommand();
        }

        return self::$instance;
    }
}
