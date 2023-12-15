<?php

declare(strict_types=1);

namespace Pest;

use Pest\Exceptions\InvalidPestCommand;
use Pest\Repositories\AfterAllRepository;
use Pest\Repositories\AfterEachRepository;
use Pest\Repositories\BeforeAllRepository;
use Pest\Repositories\BeforeEachRepository;
use Pest\Repositories\SnapshotRepository;
use Pest\Repositories\TestRepository;
use Pest\Support\Str;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class TestSuite
{
    /**
     * Holds the current test case.
     */
    public ?TestCase $test = null;

    /**
     * Holds the tests repository.
     */
    public TestRepository $tests;

    /**
     * Holds the before each repository.
     */
    public BeforeEachRepository $beforeEach;

    /**
     * Holds the before all repository.
     */
    public BeforeAllRepository $beforeAll;

    /**
     * Holds the after each repository.
     */
    public AfterEachRepository $afterEach;

    /**
     * Holds the after all repository.
     */
    public AfterAllRepository $afterAll;

    /**
     * Holds the snapshots repository.
     */
    public SnapshotRepository $snapshots;

    /**
     * Holds the root path.
     */
    public string $rootPath;

    /**
     * Holds an instance of the test suite.
     */
    private static ?TestSuite $instance = null;

    /**
     * Creates a new instance of the test suite.
     */
    public function __construct(
        string $rootPath,
        public string $testPath,
    ) {
        $this->beforeAll = new BeforeAllRepository();
        $this->beforeEach = new BeforeEachRepository();
        $this->tests = new TestRepository();
        $this->afterEach = new AfterEachRepository();
        $this->afterAll = new AfterAllRepository();
        $this->rootPath = (string) realpath($rootPath);
        $this->snapshots = new SnapshotRepository(
            implode(DIRECTORY_SEPARATOR, [$this->rootPath, $this->testPath]),
            implode(DIRECTORY_SEPARATOR, ['.pest', 'snapshots']),
        );
    }

    /**
     * Returns the current instance of the test suite.
     */
    public static function getInstance(
        ?string $rootPath = null,
        ?string $testPath = null,
    ): TestSuite {
        if (is_string($rootPath) && is_string($testPath)) {
            self::$instance = new TestSuite($rootPath, $testPath);

            foreach (Plugin::$callables as $callable) {
                $callable();
            }

            return self::$instance;
        }

        if (! self::$instance instanceof self) {
            Panic::with(new InvalidPestCommand());
        }

        return self::$instance;
    }

    public function getFilename(): string
    {
        assert($this->test instanceof TestCase);

        return (fn () => self::$__filename)->call($this->test, $this->test::class); // @phpstan-ignore-line
    }

    public function getDescription(): string
    {
        assert($this->test instanceof TestCase);

        $description = str_replace('__pest_evaluable_', '', $this->test->name());
        $datasetAsString = str_replace('__pest_evaluable_', '', Str::evaluable($this->test->dataSetAsStringWithData()));

        return str_replace(' ', '_', $description.$datasetAsString);
    }

    public function registerSnapshotChange(string $message): void
    {
        assert($this->test instanceof TestCase);

        (fn (): string => $this->__snapshotChanges[] = $message)->call($this->test, $this->test::class); // @phpstan-ignore-line
    }
}
