<?php

declare(strict_types=1);

namespace Pest\Factories;

use Closure;
use Pest\Concerns;
use Pest\Contracts\HasPrintableTestCaseName;
use Pest\Datasets;
use Pest\Support\HigherOrderMessageCollection;
use Pest\Support\NullClosure;
use Pest\TestSuite;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class TestCaseFactory
{
    /**
     * Holds the test filename.
     *
     * @readonly
     */
    public string $filename;

    /**
     * Holds the test description.
     *
     * @readonly
     */
    public string $description;

    /**
     * Holds the test closure.
     *
     * @readonly
     */
    public Closure $test;

    /**
     * Holds the dataset, if any.
     *
     * @var Closure|iterable|string|null
     */
    public $dataset;

    /**
     * The FQN of the test case class.
     */
    public string $class = TestCase::class;

    /**
     * An array of FQN of the class traits.
     *
     * @var array <int, string>
     */
    public array $traits = [
        Concerns\TestCase::class,
    ];

    /**
     * Holds the higher order messages
     *  for the factory that are proxyble.
     */
    public HigherOrderMessageCollection $factoryProxies;

    /**
     * Holds the higher order
     * messages that are proxyble.
     */
    public HigherOrderMessageCollection $proxies;

    /**
     * Holds the higher order
     * messages that are chainable.
     */
    public HigherOrderMessageCollection $chains;

    /**
     * Creates a new anonymous test case pending object.
     */
    public function __construct(string $filename, string $description, Closure $closure = null)
    {
        $this->filename    = $filename;
        $this->description = $description;
        $this->test        = $closure ?? NullClosure::create();

        $this->factoryProxies = new HigherOrderMessageCollection();
        $this->proxies        = new HigherOrderMessageCollection();
        $this->chains         = new HigherOrderMessageCollection();
    }

    /**
     * Builds the anonymous test case.
     *
     * @return array<int, TestCase>
     */
    public function build(TestSuite $testSuite): array
    {
        $chains      = $this->chains;
        $proxies     = $this->proxies;
        $factoryTest = $this->test;

        $test = function () use ($chains, $proxies, $factoryTest): void {
            $proxies->proxy($this);
            $chains->chain($this);
            call_user_func(Closure::bind($factoryTest, $this, get_class($this)), ...func_get_args());
        };

        $className = $this->makeClassFromFilename($this->filename);

        $createTest = function ($description, $data) use ($className, $test) {
            $testCase = new $className($test, $description, $data);
            $this->factoryProxies->proxy($testCase);

            return $testCase;
        };

        $datasets = Datasets::resolve($this->description, $this->dataset);

        return array_map($createTest, array_keys($datasets), $datasets);
    }

    /**
     * Makes a fully qualified class name
     * from the given filename.
     */
    public function makeClassFromFilename(string $filename): string
    {
        $rootPath     = TestSuite::getInstance()->rootPath;
        $relativePath = str_replace($rootPath . DIRECTORY_SEPARATOR, '', $filename);
        // Strip out any %-encoded octets.
        $relativePath = (string) preg_replace('|%[a-fA-F0-9][a-fA-F0-9]|', '', $relativePath);

        // Limit to A-Z, a-z, 0-9, '_', '-'.
        $relativePath = (string) preg_replace('/[^A-Za-z0-9.\/]/', '', $relativePath);

        $classFQN     = 'P\\' . basename(ucfirst(str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath)), '.php');

        if (class_exists($classFQN)) {
            return $classFQN;
        }

        $hasPrintableTestCaseClassFQN = sprintf('\%s', HasPrintableTestCaseName::class);
        $traitsCode                   = sprintf('use %s;', implode(', ', array_map(fn ($trait) => sprintf('\%s', $trait), $this->traits)));

        $partsFQN  = explode('\\', $classFQN);
        $className = array_pop($partsFQN);
        $namespace = implode('\\', $partsFQN);
        $baseClass = sprintf('\%s', $this->class);

        eval("
            namespace $namespace;

            final class $className extends $baseClass implements $hasPrintableTestCaseClassFQN {
                $traitsCode

                private static string \$__filename = '$filename';
            }
        ");

        return $classFQN;
    }
}
