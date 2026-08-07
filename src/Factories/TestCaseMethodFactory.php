<?php

declare(strict_types=1);

namespace Pest\Factories;

use Closure;
use Pest\Evaluators\Attributes;
use Pest\Exceptions\ShouldNotHappen;
use Pest\Factories\Concerns\HigherOrderable;
use Pest\Repositories\DatasetsRepository;
use Pest\Support\Description;
use Pest\Support\Str;
use Pest\TestSuite;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class TestCaseMethodFactory
{
    use HigherOrderable;

    /**
     * @var array<int, Attribute>
     */
    public array $attributes = [];

    /**
     * @var array<int, Description>
     */
    public array $describing = [];

    public ?string $description = null;

    public int $repetitions = 1;

    public ?int $flakyTries = null;

    public bool $todo = false;

    /**
     * @var array<int, int>
     */
    public array $issues = [];

    /**
     * @var array<int, string>
     */
    public array $assignees = [];

    /**
     * @var array<int, int>
     */
    public array $prs = [];

    /**
     * @var array<int, string>
     */
    public array $notes = [];

    /**
     * @var array<Closure|iterable<int|string, mixed>|string>
     */
    public array $datasets = [];

    /**
     * @var array<int, string>
     */
    public array $depends = [];

    /**
     * @var array<int, string>
     */
    public array $groups = [];

    /**
     * @see This property is not actually used in the codebase, it's only here to make Rector happy.
     */
    public bool $__ran = false;

    public function __construct(
        public string $filename,
        public ?Closure $closure,
    ) {
        $this->closure ??= function (): void {
            (Assert::getCount() > 0 || $this->doesNotPerformAssertions()) ?: self::markTestIncomplete(); // @phpstan-ignore-line
        };

        $this->bootHigherOrderable();
    }

    public function setUp(TestCase $concrete): void
    {
        $concrete::flush(); // @phpstan-ignore-line

        if ($this->description === null) {
            throw ShouldNotHappen::fromMessage('Description can not be empty.');
        }

        $testCase = TestSuite::getInstance()->tests->get($this->filename);

        assert($testCase instanceof TestCaseFactory);
        $testCase->factoryProxies->proxy($concrete);
        $this->factoryProxies->proxy($concrete);
    }

    public function tearDown(TestCase $concrete): void
    {
        $concrete::flush(); // @phpstan-ignore-line
    }

    public function getClosure(): Closure
    {
        $closure = $this->closure;
        $testCase = TestSuite::getInstance()->tests->get($this->filename);
        assert($testCase instanceof TestCaseFactory);
        $method = $this;

        return function (...$arguments) use ($testCase, $method, $closure): mixed {
            /* @var TestCase $this */
            $testCase->proxies->proxy($this);
            $method->proxies->proxy($this);

            $testCase->chains->chain($this);
            $method->chains->chain($this);

            $this->__ran = true;

            return \Pest\Support\Closure::bind($closure, $this, self::class)(...$arguments);
        };
    }

    public function receivesArguments(): bool
    {
        return $this->datasets !== [] || $this->depends !== [] || $this->repetitions > 1;
    }

    public function buildForEvaluation(): string
    {
        if ($this->description === null) {
            throw ShouldNotHappen::fromMessage('The test description may not be empty.');
        }

        $methodName = Str::evaluable($this->description);

        $datasetsCode = '';

        $this->attributes = [
            new Attribute(
                Test::class,
                [],
            ),
            new Attribute(
                TestDox::class,
                [str_replace('*/', '{@*}', $this->description)],
            ),
            ...$this->attributes,
        ];

        foreach ($this->depends as $depend) {
            $depend = Str::evaluable($this->describing === [] ? $depend : Str::describe($this->describing, $depend));

            $this->attributes[] = new Attribute(
                Depends::class,
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
                public function $methodName(...\$arguments)
                {
                    if (count(\$arguments) === 1 && \$arguments[0] instanceof __PestDatasetProviderError) {
                        throw \$arguments[0]->getPrevious() ?? \$arguments[0];
                    }

                    return \$this->__runTest(
                        \$this->__test,
                        ...\$arguments,
                    );
                }
            $datasetsCode
            PHP;
    }

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
                    try {
                        return __PestDatasets::get(self::\$__filename, "$methodName");
                    } catch (\Throwable \$throwable) {
                        return [[new __PestDatasetProviderError(\$throwable)]];
                    }
                }

        EOF;
    }
}
