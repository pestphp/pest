<?php

declare(strict_types=1);

namespace Pest\Factories;

use Closure;
use ParseError;
use Pest\Concerns;
use Pest\Contracts\HasPrintableTestCaseName;
use Pest\Datasets;
use Pest\Exceptions\ShouldNotHappen;
use Pest\Repositories\MethodProxyRepository;
use Pest\Support\HigherOrderMessageCollection;
use Pest\Support\NullClosure;
use Pest\Support\Reflection;
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
     *
     * @var string
     */
    public $filename;

    /**
     * Marks this test case as only.
     *
     * @readonly
     *
     * @var bool
     */
    public $only = false;

    /**
     * Holds the test description.
     *
     * If the description is null, means that it
     * will be created with the given assertions.
     *
     * @var string|null
     */
    public $description;

    /**
     * Holds the test closure.
     *
     * @readonly
     *
     * @var Closure
     */
    public $test;

    /**
     * Holds the dataset, if any.
     *
     * @var Closure|iterable<int, mixed>|string|null
     */
    public $dataset;

    /**
     * The FQN of the test case class.
     *
     * @var class-string
     */
    public $class = TestCase::class;

    /**
     * An array of FQN of the class traits.
     *
     * @var array <int, string>
     */
    public $traits = [
        Concerns\TestCase::class,
    ];

    /**
     * Registered methods to override from base test class.
     *
     * @var array <string, \Closure>
     */
    public $methods = [];

    /**
     * Registered properties to override from base test class.
     *
     * @var array <string, scalar|array|null>
     */
    public $properties = [];

    /**
     * Holds the higher order messages
     *  for the factory that are proxyble.
     *
     * @var HigherOrderMessageCollection
     */
    public $factoryProxies;

    /**
     * Holds the higher order
     * messages that are proxyble.
     *
     * @var HigherOrderMessageCollection
     */
    public $proxies;

    /**
     * Holds the higher order
     * messages that are chainable.
     *
     * @var HigherOrderMessageCollection
     */
    public $chains;

    /**
     * Creates a new anonymous test case pending object.
     */
    public function __construct(string $filename, string $description = null, Closure $closure = null)
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
        if ($this->description === null) {
            throw ShouldNotHappen::fromMessage('Description can not be empty.');
        }

        $chains      = $this->chains;
        $proxies     = $this->proxies;
        $factoryTest = $this->test;

        /**
         * @return mixed
         */
        $test = function () use ($chains, $proxies, $factoryTest) {
            $proxies->proxy($this);
            $chains->chain($this);

            return call_user_func(Closure::bind($factoryTest, $this, get_class($this)), ...func_get_args());
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
     * Makes a fully qualified class name from the current filename.
     */
    public function getClassName(): string
    {
        return $this->makeClassFromFilename($this->filename);
    }

    /**
     * Makes a fully qualified class name from the given filename.
     */
    public function makeClassFromFilename(string $filename): string
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            // In case Windows, strtolower drive name, like in UsesCall.
            $filename = (string) preg_replace_callback('~^(?P<drive>[a-z]+:\\\)~i', function ($match): string {
                return strtolower($match['drive']);
            }, $filename);
        }

        $filename     = (string) realpath($filename);
        $rootPath     = TestSuite::getInstance()->rootPath;
        $relativePath = str_replace($rootPath . DIRECTORY_SEPARATOR, '', $filename);
        $relativePath = dirname(ucfirst($relativePath)) . DIRECTORY_SEPARATOR . basename($relativePath, '.php');
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);

        // Strip out any %-encoded octets.
        $relativePath = (string) preg_replace('|%[a-fA-F0-9][a-fA-F0-9]|', '', $relativePath);
        // Limit to A-Z, a-z, 0-9, '_', '-'.
        $relativePath = (string) preg_replace('/[^A-Za-z0-9.\\\]/', '', $relativePath);

        /**
         * @var class-string
         */
        $classFQN = 'P\\' . $relativePath;

        $this->loadClass($classFQN, $filename);

        return $classFQN;
    }

    /**
     * Generates code for the testcase and loads it in memory
     * so we can use it, but only once.
     *
     * @param class-string $classFQN
     */
    private function loadClass(string $classFQN, string $filename): void
    {
        if (class_exists($classFQN)) {
            return;
        }

        $hasPrintableTestCaseClassFQN = sprintf('\%s', HasPrintableTestCaseName::class);

        $partsFQN  = explode('\\', $classFQN);
        $className = array_pop($partsFQN);
        $namespace = implode('\\', $partsFQN);

        /**
         * @var class-string
         */
        $baseClass = sprintf('\%s', $this->class);

        $traits     = $this->createTraits();
        $properties = $this->createProperties($baseClass);
        $methods    = $this->createMethods($classFQN, $baseClass);

        $code    = "
            namespace $namespace;

            final class $className extends $baseClass implements $hasPrintableTestCaseClassFQN {

                $traits
                $properties

                private static \$__filename = '$filename';
                
                $methods
            }
        ";

        try {
            eval($code);
        } catch (ParseError $e) {
            ShouldNotHappen::fromMessage("
                
                The code template threw a parse error: {$e->getMessage()}

                $code
                
            ");
        }
    }

    /**
     * Parses used traits to inject as code in produced testcase.
     */
    private function createTraits(): string
    {
        return sprintf('use %s;', implode(', ', array_map(function ($trait): string {
            return sprintf('\%s', $trait);
        }, $this->traits)));
    }

    /**
     * Parses used properties to inject as code in produced testcase.
     *
     * @param class-string $base
     */
    private function createProperties(string $base): string
    {
        return implode("\n", array_map(function (string $propertyName) use ($base): string {
            $static       = Reflection::isPropertyStatic($base, $propertyName) ? 'static' : '';
            $defaultValue = Reflection::encodeValue($this->properties[$propertyName]);

            return "public $static \$$propertyName = $defaultValue;";
        }, array_keys($this->properties)));
    }

    /**
     * Parses overridden methods to inject as code in produced testcase.
     *
     * @param class-string $fqn
     * @param class-string $base
     */
    private function createMethods(string $fqn, string $base): string
    {
        return implode(array_map(function (string $methodName) use ($fqn, $base): string {
            $static     = Reflection::isMethodStatic($base, $methodName) ? 'static' : '';
            $arguments  = Reflection::getMethodSignature($base, $methodName);
            $returnType = Reflection::getReturnType($base, $methodName);

            [$evaluator, $context] = ((bool) $static) ? ['staticEvaluate', 'self::class'] : ['evaluate', '$this'];

            MethodProxyRepository::register($fqn, $methodName, $this->methods[$methodName]);

            return "
                final public $static function $methodName($arguments) $returnType
                {
                    return \\Pest\\Repositories\\MethodProxyRepository::$evaluator(
                        $context,
                        '$methodName',
                        func_get_args()
                    );
                }
            ";
        }, array_keys($this->methods)));
    }
}
