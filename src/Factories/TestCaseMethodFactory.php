<?php

declare(strict_types=1);

namespace Pest\Factories;

use Closure;
use Pest\Evaluators\Attributes;
use Pest\Exceptions\ShouldNotHappen;
use Pest\Factories\Concerns\HigherOrderable;
use Pest\Repositories\DatasetsRepository;
use Pest\Support\Str;
use Pest\TestSuite;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class TestCaseMethodFactory
{
    use HigherOrderable;

    /**
     * The list of attributes.
     *
     * @var array<int, class-string<Attribute>>
     */
    public array $attributes = [];

    /**
     * The test's describing, if any.
     */
    public ?string $describing = null;

    /**
     * The test's number of repetitions.
     */
    public int $repetitions = 1;

    /**
     * Determines if the test is a "todo".
     */
    public bool $todo = false;

    /**
     * The test's datasets.
     *
     * @var array<Closure|iterable<int|string, mixed>|string>
     */
    public array $datasets = [];

    /**
     * The test's dependencies.
     *
     * @var array<int, string>
     */
    public array $depends = [];

    /**
     * The test's groups.
     *
     * @var array<int, string>
     */
    public array $groups = [];

    /**
     * Callback to run after the test has been executed.
     */
    public ?Closure $after = null;

    /**
     * Creates a new test case method factory instance.
     */
    public function __construct(
        public string $filename,
        public ?string $description,
        public ?Closure $closure,
    ) {
        $this->closure ??= function (): void {
            Assert::getCount() > 0 ?: self::markTestIncomplete(); // @phpstan-ignore-line
        };

        $this->bootHigherOrderable();
    }

    /**
     * Creates the test's closure.
     */
    public function getClosure(TestCase $concrete): Closure
    {
        $concrete::flush(); // @phpstan-ignore-line

        if ($this->description === null) {
            throw ShouldNotHappen::fromMessage('Description can not be empty.');
        }

        $closure = $this->closure;

        $testCase = TestSuite::getInstance()->tests->get($this->filename);

        $testCase->factoryProxies->proxy($concrete);
        $this->factoryProxies->proxy($concrete);

        $method = $this;

        return function () use ($testCase, $method, $closure): mixed { // @phpstan-ignore-line
            /* @var TestCase $this */
            $testCase->proxies->proxy($this);
            $method->proxies->proxy($this);

            $testCase->chains->chain($this);
            $method->chains->chain($this);

            return \Pest\Support\Closure::bind($closure, $this, self::class)(...func_get_args());
        };
    }

    /**
     * Determine if the test case will receive argument input from Pest, or not.
     */
    public function receivesArguments(): bool
    {
        return $this->datasets !== [] || $this->depends !== [];
    }

    /**
     * Creates a PHPUnit method as a string ready for evaluation.
     */
    public function buildForEvaluation(): string
    {
        if ($this->description === null) {
            throw ShouldNotHappen::fromMessage('The test description may not be empty.');
        }

        $methodName = Str::evaluable($this->description);

        $datasetsCode = '';

        // prepend attribute
        $this->attributes = [
            new Attribute(
                \PHPUnit\Framework\Attributes\Test::class,
                [],
            ),
            new Attribute(
                \PHPUnit\Framework\Attributes\TestDox::class,
                [str_replace('*/', '{@*}', $this->description)],
            ),
            ...$this->attributes,
        ];

        foreach ($this->depends as $depend) {
            $depend = Str::evaluable($this->describing !== null ? Str::describe($this->describing, $depend) : $depend);

            $this->attributes[] = new Attribute(
                \PHPUnit\Framework\Attributes\Depends::class,
                [$depend],
            );
        }

        if ($this->datasets !== [] || $this->repetitions > 1) {
            $dataProviderName = $methodName.'_dataset';
            $this->attributes[] = new Attribute(
                DataProvider::class,
                [$dataProviderName],
            );
            $datasetsCode = $this->buildDatasetForEvaluation($methodName, $dataProviderName);
        }

        $attributesCode = Attributes::code($this->attributes);

        return <<<PHP
            $attributesCode
                public function $methodName()
                {
                    \$test = \Pest\TestSuite::getInstance()->tests->get(self::\$__filename)->getMethod(\$this->name())->getClosure(\$this);

                    return \$this->__runTest(
                        \$test,
                        ...func_get_args(),
                    );
                }
            $datasetsCode
            PHP;
    }

    /**
     * Creates a PHPUnit Data Provider as a string ready for evaluation.
     */
    private function buildDatasetForEvaluation(string $methodName, string $dataProviderName): string
    {
        $datasets = $this->datasets;

        if ($this->repetitions > 1) {
            $datasets = [range(1, $this->repetitions), ...$datasets];
        }

        DatasetsRepository::with($this->filename, $methodName, $datasets);

        return <<<EOF

                public static function $dataProviderName()
                {
                    return __PestDatasets::get(self::\$__filename, "$methodName");
                }

        EOF;
    }

    /**
     * Execute the 'after' callback, if set.
     */
    public function __destruct()
    {
        if ($this->after !== null) {
            ($this->after)();
        }
    }
}
