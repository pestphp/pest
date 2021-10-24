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
 * @internal
 */
trait Testable
{
    /**
     * The Test Case description.
     */
    private string $__description;

    /**
     * The Test Case "test" closure.
     */
    private Closure $__test;

    /**
     * The Test Case "setUp" closure.
     */
    private ?Closure $__beforeEach = null;

    /**
     * The Test Case "tearDown" closure.
     */
    private ?Closure $__afterEach = null;

    /**
     * The Test Case "setUpBeforeClass" closure.
     */
    private static ?Closure $__beforeAll = null;

    /**
     * The test "tearDownAfterClass" closure.
     */
    private static ?Closure $__afterAll = null;

    /**
     * Creates a new Test Case instance.
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
     * Adds groups to the Test Case.
     */
    public function addGroups(array $groups): void
    {
        $groups = array_unique(array_merge($this->groups(), $groups));

        $this->setGroups($groups);
    }

    /**
     * Adds dependencies to the Test Case.
     */
    public function addDependencies(array $tests): void
    {
        $className = $this::class;

        $tests = array_map(static function (string $test) use ($className): ExecutionOrderDependency {
            if (!str_contains($test, '::')) {
                $test = "{$className}::{$test}";
            }

            return new ExecutionOrderDependency($test, '__test');
        }, $tests);

        $this->setDependencies($tests);
    }

    /**
     * Adds a new "setUpBeforeClass" to the Test Case.
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
     * Adds a new "tearDownAfterClass" to the Test Case.
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
     * Adds a new "setUp" to the Test Case.
     */
    public function __addBeforeEach(?Closure $hook): void
    {
        $this->__addHook('__beforeEach', $hook);
    }

    /**
     * Adds a new "tearDown" to the Test Case.
     */
    public function __addAfterEach(?Closure $hook): void
    {
        $this->__addHook('__afterEach', $hook);
    }

    /**
     * Adds a new "hook" to the Test Case.
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
     * Gets the Test Case name.
     */
    public function getName(bool $withDataSet = true): string
    {
        return (str_ends_with(Backtrace::file(), 'TestRunner.php') || Backtrace::line() === 277)
            ? '__test'
            : $this->__description;
    }

    /**
     * Gets the Test Case filename.
     */
    public static function __getFilename(): string
    {
        return self::$__filename;
    }

    /**
     * This method is called before the first test of this Test Case is run.
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
     * This method is called after the last test of this Test Case is run.
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
     * Gets executed before the Test Case.
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
     * Gets executed after the Test Case.
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
     * Gets the Test Case filename and description.
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
     * Executes the Test Case current test.
     *
     * @throws Throwable
     */
    public function __test(): mixed
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
        return array_map(fn ($data) => $data instanceof Closure ? $this->__callClosure($data, []) : $data, $arguments);
    }

    /**
     * @throws Throwable
     */
    private function __callClosure(Closure $closure, array $arguments): mixed
    {
        return ExceptionTrace::ensure(fn () => call_user_func_array(Closure::bind($closure, $this, $this::class), $arguments));
    }

    /**
     * Gets the Test Case name that should be used by printers.
     */
    public function getPrintableTestCaseName(): string
    {
        return ltrim(self::class, 'P\\');
    }
}
