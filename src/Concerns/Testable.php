<?php

declare(strict_types=1);

namespace Pest\Concerns;

use Closure;
use Pest\Support\Backtrace;
use Pest\Support\ChainableClosure;
use Pest\Support\ExceptionTrace;
use Pest\TestSuite;
use PHPUnit\Framework\ExecutionOrderDependency;
use Throwable;

/**
 * To avoid inheritance conflicts, all the fields related to Pest only will be prefixed by double underscore.
 *
 * @internal
 */
trait Testable
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
     * Holds a global/shared beforeEach ("set up") closure if one has been
     * defined.
     *
     * @var Closure|null
     */
    private $__beforeEach = null;

    /**
     * Holds a global/shared afterEach ("tear down") closure if one has been
     * defined.
     *
     * @var Closure|null
     */
    private $__afterEach = null;

    /**
     * Holds a global/shared beforeAll ("set up before") closure if one has been
     * defined.
     *
     * @var Closure|null
     */
    private static $__beforeAll = null;

    /**
     * Holds a global/shared afterAll ("tear down after") closure if one has
     * been defined.
     *
     * @var Closure|null
     */
    private static $__afterAll = null;

    /**
     * Creates a new instance of the test case.
     */
    public function __construct(Closure $test, string $description, array $data)
    {
        $this->__test          = $test;
        $this->__description   = $description;
        self::$__beforeAll     = null;
        self::$__afterAll      = null;

        parent::__construct('__test');

        $this->setData($description, $data);
    }

    /**
     * Adds the groups to the current test case.
     */
    public function addGroups(array $groups): void
    {
        $groups = array_unique(array_merge($this->groups(), $groups));

        $this->setGroups($groups);
    }

    /**
     * Add dependencies to the test case and map them to instances of ExecutionOrderDependency.
     */
    public function addDependencies(array $tests): void
    {
        $className = get_class($this);

        $tests = array_map(function (string $test) use ($className): ExecutionOrderDependency {
            if (strpos($test, '::') === false) {
                $test = "{$className}::{$test}";
            }

            return new ExecutionOrderDependency($test, '__test');
        }, $tests);

        $this->setDependencies($tests);
    }

    /**
     * Add a shared/"global" before all test hook that will execute **before**
     * the test defined `beforeAll` hook(s).
     */
    public function __addBeforeAll(?Closure $hook): void
    {
        if (!$hook) {
            return;
        }

        self::$__beforeAll = (self::$__beforeAll instanceof Closure)
            ? ChainableClosure::fromStatic(self::$__beforeAll, $hook)
            : $hook;
    }

    /**
     * Add a shared/"global" after all test hook that will execute **before**
     * the test defined `afterAll` hook(s).
     */
    public function __addAfterAll(?Closure $hook): void
    {
        if (!$hook) {
            return;
        }

        self::$__afterAll = (self::$__afterAll instanceof Closure)
            ? ChainableClosure::fromStatic(self::$__afterAll, $hook)
            : $hook;
    }

    /**
     * Add a shared/"global" before each test hook that will execute **before**
     * the test defined `beforeEach` hook.
     */
    public function __addBeforeEach(?Closure $hook): void
    {
        $this->__addHook('__beforeEach', $hook);
    }

    /**
     * Add a shared/"global" after each test hook that will execute **before**
     * the test defined `afterEach` hook.
     */
    public function __addAfterEach(?Closure $hook): void
    {
        $this->__addHook('__afterEach', $hook);
    }

    /**
     * Add a shared/global hook and compose them if more than one is passed.
     */
    private function __addHook(string $property, ?Closure $hook): void
    {
        if (!$hook) {
            return;
        }

        $this->{$property} = ($this->{$property} instanceof Closure)
            ? ChainableClosure::from($this->{$property}, $hook)
            : $hook;
    }

    /**
     * Returns the test case name. Note that, in Pest
     * we ignore withDataset argument as the description
     * already contains the dataset description.
     */
    public function getName(bool $withDataSet = true): string
    {
        return (str_ends_with(Backtrace::file(), 'TestRunner.php') || Backtrace::line() === 277)
            ? '__test'
            : $this->__description;
    }

    public static function __getFileName(): string
    {
        return self::$__filename;
    }

    /**
     * This method is called before the first test of this test class is run.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $beforeAll = TestSuite::getInstance()->beforeAll->get(self::$__filename);

        if (self::$__beforeAll instanceof Closure) {
            $beforeAll = ChainableClosure::fromStatic(self::$__beforeAll, $beforeAll);
        }

        call_user_func(Closure::bind($beforeAll, null, self::class));
    }

    /**
     * This method is called after the last test of this test class is run.
     */
    public static function tearDownAfterClass(): void
    {
        $afterAll = TestSuite::getInstance()->afterAll->get(self::$__filename);

        if (self::$__afterAll instanceof Closure) {
            $afterAll = ChainableClosure::fromStatic(self::$__afterAll, $afterAll);
        }

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

        if ($this->__beforeEach instanceof Closure) {
            $beforeEach = ChainableClosure::from($this->__beforeEach, $beforeEach);
        }

        $this->__callClosure($beforeEach, func_get_args());
    }

    /**
     * Gets executed after the test.
     */
    protected function tearDown(): void
    {
        $afterEach = TestSuite::getInstance()->afterEach->get(self::$__filename);

        if ($this->__afterEach instanceof Closure) {
            $afterEach = ChainableClosure::from($this->__afterEach, $afterEach);
        }

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
     *
     * @return mixed
     *
     * @throws Throwable
     */
    public function __test()
    {
        return $this->__callClosure($this->__test, $this->__resolveTestArguments(func_get_args()));
    }

    /**
     * Resolve the passed arguments. Any Closures will be bound to the testcase and resolved.
     *
     * @throws Throwable
     */
    private function __resolveTestArguments(array $arguments): array
    {
        return array_map(function ($data) {
            return $data instanceof Closure ? $this->__callClosure($data, []) : $data;
        }, $arguments);
    }

    /**
     * @return mixed
     *
     * @throws Throwable
     */
    private function __callClosure(Closure $closure, array $arguments)
    {
        return ExceptionTrace::ensure(function () use ($closure, $arguments) {
            return call_user_func_array(Closure::bind($closure, $this, get_class($this)), $arguments);
        });
    }

    public function getPrintableTestCaseName(): string
    {
        return ltrim(self::class, 'P\\');
    }
}
