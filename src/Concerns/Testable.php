<?php

declare(strict_types=1);

namespace Pest\Concerns;

use Closure;
use Pest\Support\ChainableClosure;
use Pest\Support\ExceptionTrace;
use Pest\Support\Reflection;
use Pest\TestSuite;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * @internal
 *
 * @mixin TestCase
 */
trait Testable
{
    /**
     * Test method description.
     */
    private static string $__description;

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
     * Resets the test case static properties.
     */
    public static function flush(): void
    {
        self::$__beforeAll = null;
        self::$__afterAll  = null;
    }

    /**
     * Creates a new Test Case instance.
     */
    public function __construct(string $name)
    {
        parent::__construct($name);

        $test = TestSuite::getInstance()->tests->get(self::$__filename);

        if ($test->hasMethod($name)) {
            $this->__test = $test->getMethod($name)->getClosure($this);
        }
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
        self::$__description = $this->name();

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
     * Executes the Test Case current test.
     *
     * @throws Throwable
     */
    private function __runTest(Closure $closure, ...$args): mixed
    {
        return $this->__callClosure($closure, $this->__resolveTestArguments($args));
    }

    /**
     * Resolve the passed arguments. Any Closures will be bound to the testcase and resolved.
     *
     * @throws Throwable
     */
    private function __resolveTestArguments(array $arguments): array
    {
        $method = TestSuite::getInstance()->tests->get(self::$__filename)->getMethod($this->name());

        if ($this->dataName()) {
            self::$__description = $method->description . ' with ' . $this->dataName();
        } else {
            self::$__description = $method->description;
        }

        if (count($arguments) !== 1) {
            return $arguments;
        }

        if (!$arguments[0] instanceof Closure) {
            return $arguments;
        }

        $underlyingTest     = Reflection::getFunctionVariable($this->__test, 'closure');
        $testParameterTypes = array_values(Reflection::getFunctionArguments($underlyingTest));

        if (in_array($testParameterTypes[0], ['Closure', 'callable'])) {
            return $arguments;
        }

        $boundDatasetResult = $this->__callClosure($arguments[0], []);

        if (count($testParameterTypes) === 1 || !is_array($boundDatasetResult)) {
            return [$boundDatasetResult];
        }

        return array_values($boundDatasetResult);
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
    public static function getPrintableTestCaseName(): string
    {
        return ltrim(self::class, 'P\\');
    }

    /**
     * Gets the Test Case name that should be used by printers.
     */
    public static function getPrintableTestCaseMethodName(): string
    {
        return self::$__description;
    }
}
