<?php

declare(strict_types=1);

use Pest\Browser\Api\ArrayablePendingAwaitablePage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Configuration;
use Pest\Exceptions\AfterAllWithinDescribe;
use Pest\Exceptions\BeforeAllWithinDescribe;
use Pest\Expectation;
use Pest\Installers\PluginBrowser;
use Pest\Mutate\Contracts\MutationTestRunner;
use Pest\Mutate\Repositories\ConfigurationRepository;
use Pest\PendingCalls\AfterEachCall;
use Pest\PendingCalls\BeforeEachCall;
use Pest\PendingCalls\DescribeCall;
use Pest\PendingCalls\TestCall;
use Pest\PendingCalls\UsesCall;
use Pest\Repositories\DatasetsRepository;
use Pest\Support\Backtrace;
use Pest\Support\Container;
use Pest\Support\DatasetInfo;
use Pest\Support\Description;
use Pest\Support\HigherOrderTapProxy;
use Pest\TestSuite;
use PHPUnit\Framework\TestCase;

if (! function_exists('expect')) {
    /**
     * @template TValue
     *
     * @param  TValue|null  $value
     * @return Expectation<TValue|null>
     */
    function expect(mixed $value = null): Expectation
    {
        return new Expectation($value);
    }
}

if (! function_exists('beforeAll')) {
    function beforeAll(Closure $closure): void
    {
        if (DescribeCall::describing() !== []) {
            $filename = Backtrace::testFile();

            throw new BeforeAllWithinDescribe($filename);
        }

        TestSuite::getInstance()->beforeAll->set($closure);
    }
}

if (! function_exists('beforeEach')) {
    /**
     * @param-closure-this TestCall  $closure
     */
    function beforeEach(?Closure $closure = null): BeforeEachCall
    {
        $filename = Backtrace::testFile();

        return new BeforeEachCall(TestSuite::getInstance(), $filename, $closure);
    }
}

if (! function_exists('dataset')) {
    /**
     * @param  Closure|iterable<int|string, mixed>  $dataset
     */
    function dataset(string $name, Closure|iterable $dataset): void
    {
        $scope = DatasetInfo::scope(Backtrace::datasetsFile());

        DatasetsRepository::set($name, $dataset, $scope);
    }
}

if (! function_exists('describe')) {
    function describe(string $description, Closure $tests): DescribeCall
    {
        $filename = Backtrace::testFile();

        return new DescribeCall(TestSuite::getInstance(), $filename, new Description($description), $tests);
    }
}

if (! function_exists('uses')) {
    /**
     * @param  class-string  ...$classAndTraits
     */
    function uses(string ...$classAndTraits): UsesCall
    {
        $filename = Backtrace::testFile();

        return new UsesCall($filename, array_values($classAndTraits));
    }
}

if (! function_exists('pest')) {
    function pest(): Configuration
    {
        return new Configuration(Backtrace::testFile());
    }
}

if (! function_exists('test')) {
    /**
     * @param-closure-this TestCall  $closure
     *
     * @return ($description is string ? TestCall : HigherOrderTapProxy|TestCall)
     */
    function test(?string $description = null, ?Closure $closure = null): HigherOrderTapProxy|TestCall
    {
        if ($description === null && TestSuite::getInstance()->test instanceof TestCase) {
            return new HigherOrderTapProxy(TestSuite::getInstance()->test);
        }

        $filename = Backtrace::testFile();

        return new TestCall(TestSuite::getInstance(), $filename, $description, $closure);
    }
}

if (! function_exists('it')) {
    /**
     * @param-closure-this TestCall  $closure
     */
    function it(string $description, ?Closure $closure = null): TestCall
    {
        $description = sprintf('it %s', $description);

        return test($description, $closure);
    }
}

if (! function_exists('todo')) {
    function todo(string $description): TestCall
    {
        return test($description)->todo();
    }
}

if (! function_exists('afterEach')) {
    /**
     * @param-closure-this TestCall  $closure
     */
    function afterEach(?Closure $closure = null): AfterEachCall
    {
        $filename = Backtrace::testFile();

        return new AfterEachCall(TestSuite::getInstance(), $filename, $closure);
    }
}

if (! function_exists('afterAll')) {
    function afterAll(Closure $closure): void
    {
        if (DescribeCall::describing() !== []) {
            $filename = Backtrace::testFile();

            throw new AfterAllWithinDescribe($filename);
        }

        TestSuite::getInstance()->afterAll->set($closure);
    }
}

if (! function_exists('covers')) {
    /**
     * @param  array<int, string>|string  $classesOrFunctions
     */
    function covers(array|string ...$classesOrFunctions): void
    {
        $filename = Backtrace::testFile();

        $beforeEachCall = (new BeforeEachCall(TestSuite::getInstance(), $filename));

        $beforeEachCall->covers(...$classesOrFunctions);
        $beforeEachCall->group('__pest_mutate_only');

        /** @var MutationTestRunner $runner */
        $runner = Container::getInstance()->get(MutationTestRunner::class);
        /** @var ConfigurationRepository $configurationRepository */
        $configurationRepository = Container::getInstance()->get(ConfigurationRepository::class);
        $everything = $configurationRepository->cliConfiguration->toArray()['everything'] ?? false;
        $classes = $configurationRepository->cliConfiguration->toArray()['classes'] ?? false;
        $paths = $configurationRepository->cliConfiguration->toArray()['paths'] ?? false;

        if ($runner->isEnabled() && ! $everything && ! is_array($classes) && ! is_array($paths)) {
            $beforeEachCall->only('__pest_mutate_only');
        }
    }
}

if (! function_exists('mutates')) {
    /**
     * @param  array<int, string>|string  $targets
     */
    function mutates(array|string ...$targets): void
    {
        $filename = Backtrace::testFile();

        $beforeEachCall = (new BeforeEachCall(TestSuite::getInstance(), $filename));
        $beforeEachCall->group('__pest_mutate_only');

        /** @var MutationTestRunner $runner */
        $runner = Container::getInstance()->get(MutationTestRunner::class);
        /** @var ConfigurationRepository $configurationRepository */
        $configurationRepository = Container::getInstance()->get(ConfigurationRepository::class);
        $everything = $configurationRepository->cliConfiguration->toArray()['everything'] ?? false;
        $classes = $configurationRepository->cliConfiguration->toArray()['classes'] ?? false;
        $paths = $configurationRepository->cliConfiguration->toArray()['paths'] ?? false;

        if ($runner->isEnabled() && ! $everything && ! is_array($classes) && ! is_array($paths)) {
            $beforeEachCall->only('__pest_mutate_only');
        }

        /** @var ConfigurationRepository $configurationRepository */
        $configurationRepository = Container::getInstance()->get(ConfigurationRepository::class);
        $paths = $configurationRepository->cliConfiguration->toArray()['paths'] ?? false;

        if (! is_array($paths)) {
            $configurationRepository->globalConfiguration('default')->class(...$targets); // @phpstan-ignore-line
        }
    }
}

if (! function_exists('fixture')) {
    function fixture(string $file): string
    {
        $file = implode(DIRECTORY_SEPARATOR, [
            TestSuite::getInstance()->rootPath,
            TestSuite::getInstance()->testPath,
            'Fixtures',
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file),
        ]);

        $fileRealPath = realpath($file);

        if ($fileRealPath === false) {
            throw new InvalidArgumentException(
                'The fixture file ['.$file.'] does not exist.',
            );
        }

        return $fileRealPath;
    }
}

if (! function_exists('visit')) {
    /**
     * @template TUrl of array<int, string>|string
     *
     * @param  TUrl  $url
     * @param  array<string, mixed>  $options
     * @return (TUrl is array<int, string> ? ArrayablePendingAwaitablePage : PendingAwaitablePage)
     */
    function visit(array|string $url, array $options = []): ArrayablePendingAwaitablePage|PendingAwaitablePage
    {
        if (! class_exists(Pest\Browser\Configuration::class)) {
            PluginBrowser::install();

            exit(0);
        }

        // @phpstan-ignore-next-line
        return test()->visit($url, $options);
    }
}
