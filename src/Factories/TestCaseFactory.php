<?php

declare(strict_types=1);

namespace Pest\Factories;

use ParseError;
use Pest\Concerns;
use Pest\Contracts\HasPrintableTestCaseName;
use Pest\Evaluators\Attributes;
use Pest\Exceptions\DatasetMissing;
use Pest\Exceptions\InvalidTestClassName;
use Pest\Exceptions\ShouldNotHappen;
use Pest\Exceptions\TestAlreadyExist;
use Pest\Exceptions\TestClosureMustNotBeStatic;
use Pest\Exceptions\TestDescriptionMissing;
use Pest\Factories\Concerns\HigherOrderable;
use Pest\Support\Reflection;
use Pest\Support\Str;
use Pest\TestSuite;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
final class TestCaseFactory
{
    use HigherOrderable;

    /**
     * @var array<int, Attribute>
     */
    public array $attributes = [];

    /**
     * @var class-string
     */
    public string $class = TestCase::class;

    /**
     * @var array<string, TestCaseMethodFactory>
     */
    public array $methods = [];

    /**
     * @var array <int, class-string>
     */
    public array $traits = [
        Concerns\Testable::class,
        Concerns\Expectable::class,
    ];

    public ?string $namespace = null;

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
     * @param  array<string, TestCaseMethodFactory>  $methods
     */
    public function evaluate(string $filename, array $methods): void
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            $filename = (string) preg_replace_callback('~^(?P<drive>[a-z]+:\\\)~i', static fn (array $match): string => strtolower($match['drive']), $filename);
        }

        $realpath = (string) realpath($filename);
        $filename = str_replace('\\\\', '\\', addslashes($realpath));
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

        $relativePath = (string) preg_replace('|%[a-fA-F0-9][a-fA-F0-9]|', '', $relativePath);
        $relativePath = str_replace(array_map(fn (string $quote): string => sprintf('\\%s', $quote), ['\'', '"']), '', $relativePath);
        $relativePath = (string) preg_replace('/[^\p{L}\p{N}\\\\]/u', '', $relativePath);

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
        $namespace = $this->namespace ?? implode('\\', $partsFQN);
        $baseClass = sprintf('\%s', $this->class);

        if (trim($className) === '') {
            $className = 'InvalidTestName'.Str::random();
        } elseif (! Str::isValidClassName($className)) {
            throw InvalidTestClassName::fromClassName($this->filename, $className);
        }

        if ($this->namespace === null) {
            foreach ($partsFQN as $partFQN) {
                if (! Str::isValidIdentifier($partFQN)) {
                    throw InvalidTestClassName::fromNamespace($this->filename, $namespace, $partFQN);
                }
            }
        }

        $this->attributes = [
            new Attribute(
                TestDox::class,
                [$this->filename],
            ),
            ...$this->attributes,
        ];

        $attributesCode = Attributes::code($this->attributes);

        $filenameLiteral = var_export($realpath, true);

        $methodsCode = implode('', array_map(
            fn (TestCaseMethodFactory $methodFactory): string => $methodFactory->buildForEvaluation(),
            $methods
        ));

        try {
            $classCode = <<<PHP
            namespace $namespace;

            use Pest\Exceptions\DatasetProviderError as __PestDatasetProviderError;
            use Pest\Repositories\DatasetsRepository as __PestDatasets;
            use Pest\TestSuite as __PestTestSuite;

            $attributesCode
            #[\AllowDynamicProperties]
            final class $className extends $baseClass implements $hasPrintableTestCaseClassFQN {
                $traitsCode

                public static \$__filename = $filenameLiteral;

                $methodsCode
            }
            PHP;

            eval($classCode);
        } catch (ParseError $caught) {
            throw new RuntimeException(sprintf(
                "Unable to create test case for test file at [%s]. \n %s",
                $filename,
                $classCode
            ), 1, $caught);
        }
    }

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
            new \ReflectionFunction($method->closure)->isStatic()
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

        throw ShouldNotHappen::fromMessage(sprintf('Method [%s] not found.', $methodName));
    }
}
