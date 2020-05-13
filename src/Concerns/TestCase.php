<?php

declare(strict_types=1);

namespace Pest\Concerns;

use Closure;
use Pest\Support\ExceptionTrace;
use Pest\TestSuite;
use PHPUnit\Util\Test;

/**
 * To avoid inheritance conflicts, all the fields related
 * to Pest only will be prefixed by double underscore.
 *
 * @internal
 */
trait TestCase
{
    /**
     * The test case description. Contains the first
     * argument of global functions like `it` and `test`.
     *
     * @var string
     */
    private $__description;

    /**
     * Holds the test closure function.
     *
     * @var Closure
     */
    private $__test;

    /**
     * Creates a new instance of the test case.
     */
    public function __construct(Closure $test, string $description, array $data)
    {
        $this->__test        = $test;
        $this->__description = $description;

        parent::__construct('__test', $data);
    }

    /**
     * Adds the groups to the current test case.
     */
    public function addGroups(array $groups): void
    {
        $groups = array_unique(array_merge($this->getGroups(), $groups));

        $this->setGroups($groups);
    }

    /**
     * Returns the test case name. Note that, in Pest
     * we ignore withDataset argument as the description
     * already contains the dataset description.
     */
    public function getName(bool $withDataSet = true): string
    {
        return $this->__description;
    }

    /**
     * This method is called before the first test of this test class is run.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $beforeAll = TestSuite::getInstance()->beforeAll->get(self::$__filename);

        call_user_func(Closure::bind($beforeAll, null, self::class));
    }

    /**
     * This method is called after the last test of this test class is run.
     */
    public static function tearDownAfterClass(): void
    {
        $afterAll = TestSuite::getInstance()->afterAll->get(self::$__filename);

        call_user_func(Closure::bind($afterAll, null, self::class));

        parent::tearDownAfterClass();
    }

    /**
     * Gets executed before the test.
     */
    protected function setUp(): void
    {
        TestSuite::getInstance()->test = $this;

        parent::setUp();

        $beforeEach = TestSuite::getInstance()->beforeEach->get(self::$__filename);

        $this->__callClosure($beforeEach, func_get_args());
    }

    /**
     * Gets executed after the test.
     */
    protected function tearDown(): void
    {
        $afterEach = TestSuite::getInstance()->afterEach->get(self::$__filename);

        $this->__callClosure($afterEach, func_get_args());

        parent::tearDown();

        TestSuite::getInstance()->test = null;
    }

    /**
     * Returns the test case as string.
     */
    public function toString(): string
    {
        return \sprintf(
            '%s::%s',
            self::$__filename,
            $this->__description
        );
    }

    /**
     * Runs the test.
     */
    public function __test(): void
    {
        $this->__callClosure($this->__test, func_get_args());
    }

    private function __callClosure(Closure $closure, array $arguments): void
    {
        ExceptionTrace::ensure(function () use ($closure, $arguments) {
            call_user_func_array(Closure::bind($closure, $this, get_class($this)), $arguments);
        });
    }

    public function getPrintableTestCaseName(): string
    {
        return ltrim(self::class, 'P\\');
    }
}
