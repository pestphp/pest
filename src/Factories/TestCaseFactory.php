<?php

declare(strict_types=1);

namespace Pest\Factories;

use ParseError;
use Pest\Concerns;
use Pest\Contracts\HasPrintableTestCaseName;
use Pest\Evaluators\Attributes;
use Pest\Exceptions\DatasetMissing;
use Pest\Exceptions\ShouldNotHappen;
use Pest\Exceptions\TestAlreadyExist;
use Pest\Exceptions\TestClosureMustNotBeStatic;
use Pest\Exceptions\TestDescriptionMissing;
use Pest\Factories\Concerns\HigherOrderable;
use Pest\Support\Reflection;
use Pest\Support\Str;
use Pest\TestSuite;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
final class TestCaseFactory
{
    use HigherOrderable;

    /**
     * The list of attributes.
     *
     * @var array<int, Attribute>
     */
    public array $attributes = [];

    /**
     * The FQN of the Test Case class.
     *
     * @var class-string
     */
    public string $class = TestCase::class;

    /**
     * The list of class methods.
     *
     * @var array<string, TestCaseMethodFactory>
     */
    public array $methods = [];

    /**
     * The list of class traits.
     *
     * @var array <int, class-string>
     */
    public array $traits = [
        Concerns\Testable::class,
        Concerns\Expectable::class,
    ];

    /**
     * Creates a new Factory instance.
     */
    public function __construct(
        public string $filename
    ) {
        $this->bootHigherOrderable();
    }

    public function make(): void
    {
        $methods = $this->methods;

        if ($methods !== []) {
            $this->evaluate($this->filename, $methods);
        }
    }

    /**
     * Creates a Test Case class using a runtime evaluate.
     *
     * @param  array<string, TestCaseMethodFactory>  $methods
     */
    public function evaluate(string $filename, array $methods): void
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            // In case Windows, strtolower drive name, like in UsesCall.
            $filename = (string) preg_replace_callback('~^(?P<drive>[a-z]+:\\\)~i', static fn (array $match): string => strtolower($match['drive']), $filename);
        }

        $filename = str_replace('\\\\', '\\', addslashes((string) realpath($filename)));
        $rootPath = TestSuite::getInstance()->rootPath;
        $relativePath = str_replace($rootPath.DIRECTORY_SEPARATOR, '', $filename);

        $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);

        $basename = basename($relativePath, '.php');

        $dotPos = strpos($basename, '.');

        if ($dotPos !== false) {
            $basename = substr($basename, 0, $dotPos);
        }

        $relativePath = dirname(ucfirst($relativePath)).DIRECTORY_SEPARATOR.$basename;

        $relativePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);

        // Strip out any %-encoded octets.
        $relativePath = (string) preg_replace('|%[a-fA-F0-9][a-fA-F0-9]|', '', $relativePath);
        // Remove escaped quote sequences (maintain namespace)
        $relativePath = str_replace(array_map(fn (string $quote): string => sprintf('\\%s', $quote), ['\'', '"']), '', $relativePath);
        // Limit to A-Z, a-z, 0-9, '_', '-'.
        $relativePath = (string) preg_replace('/[^A-Za-z0-9\\\\]/', '', $relativePath);

        $classFQN = 'P\\'.$relativePath;

        if (class_exists($classFQN)) {
            return;
        }

        $hasPrintableTestCaseClassFQN = sprintf('\%s', HasPrintableTestCaseName::class);
        $traitsCode = sprintf('use %s;', implode(', ', array_map(
            static fn (string $trait): string => sprintf('\%s', $trait), $this->traits))
        );

        $partsFQN = explode('\\', $classFQN);
        $className = array_pop($partsFQN);
        $namespace = implode('\\', $partsFQN);
        $baseClass = sprintf('\%s', $this->class);

        if (trim($className) === '') {
            $className = 'InvalidTestName'.Str::random();
        }

        $this->attributes = [
            new Attribute(
                \PHPUnit\Framework\Attributes\TestDox::class,
                [$this->filename],
            ),
            ...$this->attributes,
        ];

        $attributesCode = Attributes::code($this->attributes);

        $methodsCode = implode('', array_map(
            fn (TestCaseMethodFactory $methodFactory): string => $methodFactory->buildForEvaluation(),
            $methods
        ));

        try {
            $classCode = <<<PHP
            namespace $namespace;

            use Pest\Repositories\DatasetsRepository as __PestDatasets;
            use Pest\TestSuite as __PestTestSuite;

            $attributesCode
            #[\AllowDynamicProperties]
            final class $className extends $baseClass implements $hasPrintableTestCaseClassFQN {
                $traitsCode

                private static \$__filename = '$filename';

                $methodsCode
            }
            PHP;

            eval($classCode);
        } catch (ParseError $caught) {
            throw new RuntimeException(sprintf(
                "Unable to create test case for test file at %s. \n %s",
                $filename,
                $classCode
            ), 1, $caught);
        }
    }

    /**
     * Adds the given Method to the Test Case.
     */
    public function addMethod(TestCaseMethodFactory $method): void
    {
        if ($method->description === null) {
            throw new TestDescriptionMissing($method->filename);
        }

        if (array_key_exists($method->description, $this->methods)) {
            throw new TestAlreadyExist($method->filename, $method->description);
        }

        if (
            $method->closure instanceof \Closure &&
            (new \ReflectionFunction($method->closure))->isStatic()
        ) {

            throw new TestClosureMustNotBeStatic($method);
        }

        if (! $method->receivesArguments()) {
            if (! $method->closure instanceof \Closure) {
                throw ShouldNotHappen::fromMessage('The test closure may not be empty.');
            }

            $arguments = Reflection::getFunctionArguments($method->closure);

            if ($arguments !== []) {
                throw new DatasetMissing($method->filename, $method->description, $arguments);
            }
        }

        $this->methods[$method->description] = $method;
    }

    /**
     * Checks if a test case has a method.
     */
    public function hasMethod(string $methodName): bool
    {
        foreach ($this->methods as $method) {
            if ($method->description === null) {
                throw ShouldNotHappen::fromMessage('The test description may not be empty.');
            }

            if ($methodName === Str::evaluable($method->description)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets a Method by the given name.
     */
    public function getMethod(string $methodName): TestCaseMethodFactory
    {
        foreach ($this->methods as $method) {
            if ($method->description === null) {
                throw ShouldNotHappen::fromMessage('The test description may not be empty.');
            }

            if ($methodName === Str::evaluable($method->description)) {
                return $method;
            }
        }

        throw ShouldNotHappen::fromMessage(sprintf('Method %s not found.', $methodName));
    }
}
