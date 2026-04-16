<?php

declare(strict_types=1);

namespace Pest\Concerns;

use Closure;
use Pest\Exceptions\DatasetArgumentsMismatch;
use Pest\Panic;
use Pest\Preset;
use Pest\Support\ChainableClosure;
use Pest\Support\ExceptionTrace;
use Pest\Support\Reflection;
use Pest\Support\Shell;
use Pest\TestSuite;
use PHPUnit\Framework\Attributes\PostCondition;
use PHPUnit\Framework\IncompleteTest;
use PHPUnit\Framework\SkippedTest;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionFunction;
use ReflectionParameter;
use Throwable;

/**
 * @internal
 *
 * @mixin TestCase
 */
trait Testable
{
    /**
     * The test's description.
     */
    private string $__description;

    /**
     * The test's latest description.
     */
    private static string $__latestDescription;

    /**
     * The test's assignees.
     */
    private static array $__latestAssignees = [];

    /**
     * The test's notes.
     */
    private static array $__latestNotes = [];

    /**
     * The test's issues.
     *
     * @var array<int, int>
     */
    private static array $__latestIssues = [];

    /**
     * The test's PRs.
     *
     * @var array<int, int>
     */
    private static array $__latestPrs = [];

    /**
     * The test's describing, if any.
     *
     * @var array<int, string>
     */
    public array $__describing = [];

    /**
     * Whether the test has ran or not.
     */
    public bool $__ran = false;

    /**
     * The test's test closure.
     */
    private Closure $__test;

    /**
     * The test's before each closure.
     */
    private ?Closure $__beforeEach = null;

    /**
     * The test's after each closure.
     */
    private ?Closure $__afterEach = null;

    /**
     * The test's before all closure.
     */
    private static ?Closure $__beforeAll = null;

    /**
     * The test's after all closure.
     */
    private static ?Closure $__afterAll = null;

    /**
     * The list of snapshot changes, if any.
     */
    private array $__snapshotChanges = [];

    /**
     * Resets the test case static properties.
     */
    public static function flush(): void
    {
        self::$__beforeAll = null;
        self::$__afterAll = null;
    }

    /**
     * Adds a new "note" to the Test Case.
     */
    public function note(array|string $note): self
    {
        $note = is_array($note) ? $note : [$note];

        self::$__latestNotes = array_merge(self::$__latestNotes, $note);

        return $this;
    }

    /**
     * Adds a new "setUpBeforeClass" to the Test Case.
     */
    public function __addBeforeAll(?Closure $hook): void
    {
        if (! $hook instanceof Closure) {
            return;
        }

        self::$__beforeAll = (self::$__beforeAll instanceof Closure)
            ? ChainableClosure::boundStatically(self::$__beforeAll, $hook)
            : $hook;
    }

    /**
     * Adds a new "tearDownAfterClass" to the Test Case.
     */
    public function __addAfterAll(?Closure $hook): void
    {
        if (! $hook instanceof Closure) {
            return;
        }

        self::$__afterAll = (self::$__afterAll instanceof Closure)
            ? ChainableClosure::boundStatically(self::$__afterAll, $hook)
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
        if (! $hook instanceof Closure) {
            return;
        }

        $this->{$property} = ($this->{$property} instanceof Closure)
            ? ChainableClosure::bound($this->{$property}, $hook)
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
            $beforeAll = ChainableClosure::boundStatically(self::$__beforeAll, $beforeAll);
        }

        try {
            call_user_func(Closure::bind($beforeAll, null, self::class));
        } catch (Throwable $e) {
            Panic::with($e);
        }
    }

    /**
     * This method is called after the last test of this Test Case is run.
     */
    public static function tearDownAfterClass(): void
    {
        $afterAll = TestSuite::getInstance()->afterAll->get(self::$__filename);

        if (self::$__afterAll instanceof Closure) {
            $afterAll = ChainableClosure::boundStatically(self::$__afterAll, $afterAll);
        }

        call_user_func(Closure::bind($afterAll, null, self::class));

        parent::tearDownAfterClass();
    }

    /**
     * Gets executed before the Test Case.
     */
    protected function setUp(...$arguments): void
    {
        TestSuite::getInstance()->test = $this;

        $method = TestSuite::getInstance()->tests->get(self::$__filename)->getMethod($this->name());

        $description = $method->description;
        if ($this->dataName()) {
            $description = str_contains((string) $description, ':dataset')
                ? str_replace(':dataset', str_replace('dataset ', '', $this->dataName()), (string) $description)
                : $description.' with '.$this->dataName();
        }

        $description = htmlspecialchars(html_entity_decode((string) $description), ENT_NOQUOTES);

        if ($method->repetitions > 1) {
            $matches = [];
            preg_match('/\((.*?)\)/', $description, $matches);

            if (count($matches) > 1) {
                if (str_contains($description, 'with '.$matches[0].' /')) {
                    $description = str_replace('with '.$matches[0].' /', '', $description);
                } else {
                    $description = str_replace('with '.$matches[0], '', $description);
                }
            }

            $description .= ' @ repetition '.($matches[1].' of '.$method->repetitions);
        }

        $this->__description = self::$__latestDescription = $description;
        self::$__latestAssignees = $method->assignees;
        self::$__latestNotes = $method->notes;
        self::$__latestIssues = $method->issues;
        self::$__latestPrs = $method->prs;

        parent::setUp();

        $beforeEach = TestSuite::getInstance()->beforeEach->get(self::$__filename)[1];

        if ($this->__beforeEach instanceof Closure) {
            $beforeEach = ChainableClosure::bound($this->__beforeEach, $beforeEach);
        }

        $this->__callClosure($beforeEach, $arguments);
    }

    /**
     * Initialize test case properties from TestSuite.
     */
    public function __initializeTestCase(): void
    {
        // Return if the test case has already been initialized
        if (isset($this->__test)) {
            return;
        }

        $name = $this->name();
        $test = TestSuite::getInstance()->tests->get(self::$__filename);

        if ($test->hasMethod($name)) {
            $method = $test->getMethod($name);
            $this->__description = self::$__latestDescription = $method->description;
            self::$__latestAssignees = $method->assignees;
            self::$__latestNotes = $method->notes;
            self::$__latestIssues = $method->issues;
            self::$__latestPrs = $method->prs;
            $this->__describing = $method->describing;
            $this->__test = $method->getClosure();

            $method->setUp($this);
        }
    }

    /**
     * Gets executed after the Test Case.
     */
    protected function tearDown(...$arguments): void
    {
        $afterEach = TestSuite::getInstance()->afterEach->get(self::$__filename);

        if ($this->__afterEach instanceof Closure) {
            $afterEach = ChainableClosure::bound($this->__afterEach, $afterEach);
        }

        try {
            $this->__callClosure($afterEach, func_get_args());
        } finally {
            parent::tearDown();

            TestSuite::getInstance()->test = null;

            $method = TestSuite::getInstance()->tests->get(self::$__filename)->getMethod($this->name());
            $method->tearDown($this);
        }
    }

    /**
     * Executes the Test Case current test.
     *
     * @throws Throwable
     */
    private function __runTest(Closure $closure, ...$args): mixed
    {
        $arguments = $this->__resolveTestArguments($args);
        $this->__ensureDatasetArgumentNameAndNumberMatches($arguments);

        $method = TestSuite::getInstance()->tests->get(self::$__filename)->getMethod($this->name());

        if ($method->flakyTries === null) {
            return $this->__callClosure($closure, $arguments);
        }

        $lastException = null;
        $initialProperties = get_object_vars($this);

        for ($attempt = 1; $attempt <= $method->flakyTries; $attempt++) {
            try {
                return $this->__callClosure($closure, $arguments);
            } catch (Throwable $e) {
                if ($e instanceof SkippedTest
                    || $e instanceof IncompleteTest
                    || $this->__isExpectedException($e)) {
                    throw $e;
                }

                $lastException = $e;

                if ($attempt < $method->flakyTries) {
                    if ($this->__snapshotChanges !== []) {
                        throw $e;
                    }

                    $this->tearDown();

                    Closure::bind(fn (): array => $this->mockObjects = [], $this, TestCase::class)();

                    foreach (array_keys(array_diff_key(get_object_vars($this), $initialProperties)) as $property) {
                        unset($this->{$property});
                    }

                    $hasOutputExpectation = Closure::bind(fn (): bool => is_string($this->outputExpectedString) || is_string($this->outputExpectedRegex), $this, TestCase::class)();

                    if ($hasOutputExpectation) {
                        ob_clean();
                    }

                    $this->setUp();
                }
            }
        }

        throw $lastException;
    }

    /**
     * Determines if the given exception matches PHPUnit's expected exception.
     */
    private function __isExpectedException(Throwable $e): bool
    {
        $read = fn (string $property): mixed => Closure::bind(fn () => $this->{$property}, $this, TestCase::class)();

        $expectedClass = $read('expectedException');

        if ($expectedClass !== null) {
            return $e instanceof $expectedClass;
        }

        $expectedMessage = $read('expectedExceptionMessage');

        if ($expectedMessage !== null) {
            return str_contains($e->getMessage(), (string) $expectedMessage);
        }

        $expectedCode = $read('expectedExceptionCode');

        if ($expectedCode !== null) {
            return $e->getCode() === $expectedCode;
        }

        return false;
    }

    /**
     * Resolve the passed arguments. Any Closures will be bound to the testcase and resolved.
     *
     * @throws Throwable
     */
    private function __resolveTestArguments(array $arguments): array
    {
        $method = TestSuite::getInstance()->tests->get(self::$__filename)->getMethod($this->name());

        if ($method->repetitions > 1) {
            // If the test is repeated, the first argument is the iteration number
            // we need to move it to the end of the arguments list
            // so that the datasets are the first n arguments
            // and the iteration number is the last argument
            $firstArgument = array_shift($arguments);
            $arguments[] = $firstArgument;
        }

        $underlyingTest = Reflection::getFunctionVariable($this->__test, 'closure');
        $testParameterTypesByName = Reflection::getFunctionArguments($underlyingTest);
        $testParameterTypes = array_values($testParameterTypesByName);

        if (count($arguments) !== 1) {
            foreach ($arguments as $argumentIndex => $argumentValue) {
                if (! $argumentValue instanceof Closure) {
                    continue;
                }

                $parameterType = is_string($argumentIndex)
                    ? $testParameterTypesByName[$argumentIndex]
                    : $testParameterTypes[$argumentIndex];

                if (in_array($parameterType, [Closure::class, 'callable', 'mixed'])) {
                    continue;
                }

                $arguments[$argumentIndex] = $this->__callClosure($argumentValue, []);
            }

            return $arguments;
        }

        if (! isset($arguments[0]) || ! $arguments[0] instanceof Closure) {
            return $arguments;
        }

        if (isset($testParameterTypes[0]) && in_array($testParameterTypes[0], [Closure::class, 'callable'])) {
            return $arguments;
        }

        $boundDatasetResult = $this->__callClosure($arguments[0], []);
        if (count($testParameterTypes) === 1) {
            return [$boundDatasetResult];
        }
        if (! is_array($boundDatasetResult)) {
            return [$boundDatasetResult];
        }

        return $boundDatasetResult;
    }

    /**
     * Ensures dataset items count matches underlying test case required parameters
     *
     * @throws ReflectionException
     * @throws DatasetArgumentsMismatch
     */
    private function __ensureDatasetArgumentNameAndNumberMatches(array $arguments): void
    {
        if ($arguments === []) {
            return;
        }

        $underlyingTest = Reflection::getFunctionVariable($this->__test, 'closure');
        $testReflection = new ReflectionFunction($underlyingTest);
        $requiredParametersCount = $testReflection->getNumberOfRequiredParameters();
        $suppliedParametersCount = count($arguments);

        $datasetParameterNames = array_keys($arguments);
        $testParameterNames = array_map(
            fn (ReflectionParameter $reflectionParameter): string => $reflectionParameter->getName(),
            array_filter($testReflection->getParameters(), fn (ReflectionParameter $reflectionParameter): bool => ! $reflectionParameter->isOptional()),
        );

        if (array_diff($testParameterNames, $datasetParameterNames) === []) {
            return;
        }

        if (isset($testParameterNames[0]) && $suppliedParametersCount >= $requiredParametersCount) {
            return;
        }

        throw new DatasetArgumentsMismatch($requiredParametersCount, $suppliedParametersCount);
    }

    /**
     * @throws Throwable
     */
    private function __callClosure(Closure $closure, array $arguments): mixed
    {
        return ExceptionTrace::ensure(fn (): mixed => call_user_func_array(Closure::bind($closure, $this, $this::class), $arguments));
    }

    /**
     * Uses the given preset on the test.
     */
    public function preset(): Preset
    {
        return new Preset;
    }

    #[PostCondition]
    protected function __MarkTestIncompleteIfSnapshotHaveChanged(): void
    {
        if (count($this->__snapshotChanges) === 0) {
            return;
        }

        $this->markTestIncomplete(implode('. ', $this->__snapshotChanges));
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
        return self::$__latestDescription ?? '';
    }

    /**
     * The printable test case method context.
     */
    public static function getPrintableContext(): array
    {
        return [
            'assignees' => self::$__latestAssignees,
            'issues' => self::$__latestIssues,
            'prs' => self::$__latestPrs,
            'notes' => self::$__latestNotes,
        ];
    }

    /**
     * Opens a shell for the test case.
     */
    public function shell(): void
    {
        Shell::open();
    }
}
