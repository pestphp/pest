<?php

declare(strict_types=1);

namespace Pest\Factories;

use Closure;
use ParseError;
use Pest\Concerns;
use Pest\Contracts\HasPrintableTestCaseName;
use Pest\Datasets;
use Pest\Exceptions\ShouldNotHappen;
use Pest\Support\HigherOrderMessageCollection;
use Pest\Support\Str;
use Pest\TestSuite;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
     * @var array<Closure|iterable<int|string, mixed>|string>
     */
    public $datasets = [];

    /**
     * The FQN of the test case class.
     *
     * @var string
     */
    public $class = TestCase::class;

    /**
     * An array of FQN of the class traits.
     *
     * @var array <int, string>
     */
    public $traits = [
        Concerns\Testable::class,
        Concerns\Expectable::class,
    ];

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
        $this->test        = $closure ?? function (): void {
            if (Assert::getCount() === 0) {
                self::markTestIncomplete(); // @phpstan-ignore-line
            }
        };

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

            /* @phpstan-ignore-next-line */
            return call_user_func(Closure::bind($factoryTest, $this, get_class($this)), ...func_get_args());
        };

        $className = $this->makeClassFromFilename($this->filename);

        $createTest = function ($description, $data) use ($className, $test) {
            $testCase = new $className($test, $description, $data);
            $this->factoryProxies->proxy($testCase);

            return $testCase;
        };

        $datasets = Datasets::resolve($this->description, $this->datasets);

        return array_map($createTest, array_keys($datasets), $datasets);
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

        $filename     = str_replace('\\\\', '\\', addslashes((string) realpath($filename)));
        $rootPath     = TestSuite::getInstance()->rootPath;
        $relativePath = str_replace($rootPath . DIRECTORY_SEPARATOR, '', $filename);
        $relativePath = dirname(ucfirst($relativePath)) . DIRECTORY_SEPARATOR . basename($relativePath, '.php');
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);

        // Strip out any %-encoded octets.
        $relativePath = (string) preg_replace('|%[a-fA-F0-9][a-fA-F0-9]|', '', $relativePath);
        // Remove escaped quote sequences (maintain namespace)
        $relativePath = str_replace(array_map(function (string $quote): string {
            return sprintf('\\%s', $quote);
        }, ['\'', '"']), '', $relativePath);
        // Limit to A-Z, a-z, 0-9, '_', '-'.
        $relativePath = (string) preg_replace('/[^A-Za-z0-9\\\\]/', '', $relativePath);

        $classFQN = 'P\\' . $relativePath;
        if (class_exists($classFQN)) {
            return $classFQN;
        }

        $hasPrintableTestCaseClassFQN = sprintf('\%s', HasPrintableTestCaseName::class);
        $traitsCode                   = sprintf('use %s;', implode(', ', array_map(function ($trait): string {
            return sprintf('\%s', $trait);
        }, $this->traits)));

        $partsFQN  = explode('\\', $classFQN);
        $className = array_pop($partsFQN);
        $namespace = implode('\\', $partsFQN);
        $baseClass = sprintf('\%s', $this->class);

        if ('' === trim($className)) {
            $className = 'InvalidTestName' . Str::random();
            $classFQN .= $className;
        }

        try {
            eval("
                namespace $namespace;

                #[\AllowDynamicProperties]
                final class $className extends $baseClass implements $hasPrintableTestCaseClassFQN {
                    $traitsCode

                    private static \$__filename = '$filename';
                }
            ");
        } catch (ParseError $caught) {
            throw new RuntimeException(sprintf('Unable to create test case for test file at %s', $filename), 1, $caught);
        }

        return $classFQN;
    }

    /**
     * Determine if the test case will receive argument input from Pest, or not.
     */
    public function receivesArguments(): bool
    {
        return count($this->datasets) > 0
            || $this->factoryProxies->count('addDependencies') > 0;
    }
}
