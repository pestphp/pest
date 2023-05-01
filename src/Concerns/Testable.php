<?php

declare(strict_types=1);

namespace Pest\Concerns;

use Closure;
use Pest\Exceptions\DatasetArgsCountMismatch;
use Pest\Support\ChainableClosure;
use Pest\Support\ExceptionTrace;
use Pest\Support\Reflection;
use Pest\TestSuite;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionFunction;
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
    private string $__description;

    /**
     * Test "latest" method description.
     */
    private static string $__latestDescription;

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
        self::$__afterAll = null;
    }

    /**
     * Creates a new Test Case instance.
     */
    public function __construct(string $name)
    {
        parent::__construct($name);

        $test = TestSuite::getInstance()->tests->get(self::$__filename);

        if ($test->hasMethod($name)) {
            $method = $test->getMethod($name);
            $this->__description = self::$__latestDescription = $method->description;
            $this->__test = $method->getClosure($this);
        }
    }

    /**
     * Adds a new "setUpBeforeClass" to the Test Case.
     */
    public function __addBeforeAll(?Closure $hook): void
    {
        if (! $hook instanceof \Closure) {
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
        if (! $hook instanceof \Closure) {
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
        if (! $hook instanceof \Closure) {
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
        TestSuite::getInstance()->test = $this;

        parent::setUp();

        $beforeEach = TestSuite::getInstance()->beforeEach->get(self::$__filename)[1];

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
        $arguments = $this->__resolveTestArguments($args);
        $this->__ensureDatasetArgumentNumberMatches($arguments);

        return $this->__callClosure($closure, $arguments);
    }

    /**
     * Resolve the passed arguments. Any Closures will be bound to the testcase and resolved.
     *
     * @throws Throwable
     */
    private function __resolveTestArguments(array $arguments): array
    {
        $method = TestSuite::getInstance()->tests->get(self::$__filename)->getMethod($this->name());

        $this->__description = self::$__latestDescription = $this->dataName() ? $method->description.' with '.$this->dataName() : $method->description;

        $underlyingTest = Reflection::getFunctionVariable($this->__test, 'closure');
        $testParameterTypes = array_values(Reflection::getFunctionArguments($underlyingTest));

        if (count($arguments) !== 1) {
            foreach ($arguments as $argumentIndex => $argumentValue) {
                if (! $argumentValue instanceof Closure) {
                    continue;
                }

                if (in_array($testParameterTypes[$argumentIndex], [Closure::class, 'callable', 'mixed'])) {
                    continue;
                }

                $arguments[$argumentIndex] = $this->__callClosure($argumentValue, []);
            }

            return $arguments;
        }

        if (! $arguments[0] instanceof Closure) {
            return $arguments;
        }

        if (in_array($testParameterTypes[0], [Closure::class, 'callable'])) {
            return $arguments;
        }

        $boundDatasetResult = $this->__callClosure($arguments[0], []);
        if (count($testParameterTypes) === 1) {
            return [$boundDatasetResult];
        }
        if (! is_array($boundDatasetResult)) {
            return [$boundDatasetResult];
        }

        return array_values($boundDatasetResult);
    }

    /**
     * Ensures dataset items count matches underlying test case required parameters
     *
     * @throws ReflectionException
     * @throws DatasetArgsCountMismatch
     */
    private function __ensureDatasetArgumentNumberMatches(array $arguments): void
    {
        if ($arguments === []) {
            return;
        }

        $underlyingTest = Reflection::getFunctionVariable($this->__test, 'closure');
        $testReflection = new ReflectionFunction($underlyingTest);
        $requiredParametersCount = $testReflection->getNumberOfRequiredParameters();
        $suppliedParametersCount = count($arguments);

        if ($suppliedParametersCount >= $requiredParametersCount) {
            return;
        }

        throw new DatasetArgsCountMismatch($requiredParametersCount, $suppliedParametersCount);
    }

    /**
     * @throws Throwable
     */
    private function __callClosure(Closure $closure, array $arguments): mixed
    {
        return ExceptionTrace::ensure(fn (): mixed => call_user_func_array(Closure::bind($closure, $this, $this::class), $arguments));
    }

    /**
     * The printable test case name.
     */
    public static function getPrintableTestCaseName(): string
    {
        return preg_replace('/P\\\/', '', self::class, 1);
    }

    /**
     * The printable test case method name.
     */
    public function getPrintableTestCaseMethodName(): string
    {
        return $this->__description;
    }

    /**
     * The latest printable test case method name.
     */
    public static function getLatestPrintableTestCaseMethodName(): string
    {
        return self::$__latestDescription;
    }
}
