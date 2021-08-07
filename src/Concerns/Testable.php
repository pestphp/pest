<?php

declare(strict_types=1);

namespace Pest\Concerns;

use Closure;
use Pest\Support\ChainableClosure;
use Pest\Support\ExceptionTrace;
use Pest\TestSuite;
use PHPUnit\Framework\ExecutionOrderDependency;
use Throwable;

/**
 * To avoid inheritance conflicts, all the fields related
 * to Pest only will be prefixed by double underscore.
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
    private $beforeEach = null;

    /**
     * Holds a global/shared afterEach ("tear down") closure if one has been
     * defined.
     *
     * @var Closure|null
     */
    private $afterEach = null;

    /**
     * Holds a global/shared beforeAll ("set up before") closure if one has been
     * defined.
     *
     * @var Closure|null
     */
    private static $beforeAll = null;

    /**
     * Holds a global/shared afterAll ("tear down after") closure if one has
     * been defined.
     *
     * @var Closure|null
     */
    private static $afterAll = null;

    /**
     * Creates a new instance of the test case.
     */
    public function __construct(Closure $test, string $description, array $data)
    {
        $this->__test        = $test;
        $this->__description = $description;
        self::$beforeAll     = null;
        self::$afterAll      = null;

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
     * Add dependencies to the test case and map them to instances of ExecutionOrderDependency.
     */
    public function addDependencies(array $tests): void
    {
        $className = get_class($this);

        $tests = array_map(function (string $test) use ($className): ExecutionOrderDependency {
            if (strpos($test, '::') === false) {
                $test = "{$className}::{$test}";
            }

            return new ExecutionOrderDependency($test, null, '');
        }, $tests);

        $this->setDependencies($tests);
    }

    /**
     * Add a shared/"global" before all test hook that will execute **before**
     * the test defined `beforeAll` hook(s).
     */
    public function addBeforeAll(?Closure $hook): void
    {
        if (!$hook) {
            return;
        }

        self::$beforeAll = (self::$beforeAll instanceof Closure)
            ? ChainableClosure::fromStatic(self::$beforeAll, $hook)
            : $hook;
    }

    /**
     * Add a shared/"global" after all test hook that will execute **before**
     * the test defined `afterAll` hook(s).
     */
    public function addAfterAll(?Closure $hook): void
    {
        if (!$hook) {
            return;
        }

        self::$afterAll = (self::$afterAll instanceof Closure)
            ? ChainableClosure::fromStatic(self::$afterAll, $hook)
            : $hook;
    }

    /**
     * Add a shared/"global" before each test hook that will execute **before**
     * the test defined `beforeEach` hook.
     */
    public function addBeforeEach(?Closure $hook): void
    {
        $this->addHook('beforeEach', $hook);
    }

    /**
     * Add a shared/"global" after each test hook that will execute **before**
     * the test defined `afterEach` hook.
     */
    public function addAfterEach(?Closure $hook): void
    {
        $this->addHook('afterEach', $hook);
    }

    /**
     * Add a shared/global hook and compose them if more than one is passed.
     */
    private function addHook(string $property, ?Closure $hook): void
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
        return $this->__description;
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

        if (self::$beforeAll instanceof Closure) {
            $beforeAll = ChainableClosure::fromStatic(self::$beforeAll, $beforeAll);
        }

        call_user_func(Closure::bind($beforeAll, null, self::class));
    }

    /**
     * This method is called after the last test of this test class is run.
     */
    public static function tearDownAfterClass(): void
    {
        $afterAll = TestSuite::getInstance()->afterAll->get(self::$__filename);

        if (self::$afterAll instanceof Closure) {
            $afterAll = ChainableClosure::fromStatic(self::$afterAll, $afterAll);
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

        if ($this->beforeEach instanceof Closure) {
            $beforeEach = ChainableClosure::from($this->beforeEach, $beforeEach);
        }

        $this->__callClosure($beforeEach, func_get_args());
    }

    /**
     * Gets executed after the test.
     */
    protected function tearDown(): void
    {
        $afterEach = TestSuite::getInstance()->afterEach->get(self::$__filename);

        if ($this->afterEach instanceof Closure) {
            $afterEach = ChainableClosure::from($this->afterEach, $afterEach);
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
        return $this->__callClosure($this->__test, $this->resolveTestArguments(func_get_args()));
    }

    /**
     * Resolve the passed arguments. Any Closures will be bound to the testcase and resolved.
     *
     * @throws Throwable
     */
    private function resolveTestArguments(array $arguments): array
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
