<?php

declare(strict_types=1);

namespace Pest\Concerns;

use Closure;
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
     */
    private string $__description;

    /**
     * Holds the test closure function.
     */
    private Closure $__test;

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
        parent::setUp();

        $beforeEach = TestSuite::getInstance()->beforeEach->get(self::$__filename);

        call_user_func(Closure::bind($beforeEach, $this, get_class($this)));
    }

    /**
     * Gets executed after the test.
     */
    protected function tearDown(): void
    {
        $afterEach = TestSuite::getInstance()->afterEach->get(self::$__filename);

        call_user_func(Closure::bind($afterEach, $this, get_class($this)));

        parent::tearDown();
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
        call_user_func(Closure::bind($this->__test, $this, get_class($this)), ...func_get_args());
    }

    public function getPrintableTestCaseName(): string
    {
        return ltrim(self::class, 'P\\');
    }
}
