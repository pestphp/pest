<?php

declare(strict_types=1);

namespace Pest\PendingCalls;

use Closure;
use Pest\Concerns\Testable;
use Pest\Exceptions\InvalidArgumentException;
use Pest\Exceptions\TestDescriptionMissing;
use Pest\Factories\Attribute;
use Pest\Factories\TestCaseMethodFactory;
use Pest\Mutate\Repositories\ConfigurationRepository;
use Pest\PendingCalls\Concerns\Describable;
use Pest\Plugins\Only;
use Pest\Support\Backtrace;
use Pest\Support\Container;
use Pest\Support\Exporter;
use Pest\Support\HigherOrderCallables;
use Pest\Support\NullClosure;
use Pest\Support\Str;
use Pest\TestSuite;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @mixin HigherOrderCallables|TestCase|Testable
 */
final class TestCall // @phpstan-ignore-line
{
    use Describable;

    /**
     * The list of test case factory attributes.
     *
     * @var array<int, Attribute>
     */
    private array $testCaseFactoryAttributes = [];

    /**
     * The Test Case Factory.
     */
    public readonly TestCaseMethodFactory $testCaseMethod;

    /**
     * If test call is descriptionLess.
     */
    private readonly bool $descriptionLess;

    /**
     * Creates a new Pending Call.
     */
    public function __construct(
        private readonly TestSuite $testSuite,
        private readonly string $filename,
        private ?string $description = null,
        ?Closure $closure = null
    ) {
        $this->testCaseMethod = new TestCaseMethodFactory($filename, $closure);

        $this->descriptionLess = $description === null;

        $this->describing = DescribeCall::describing();

        $this->testSuite->beforeEach->get($this->filename)[0]($this);
    }

    /**
     * Runs the given closure after the test.
     */
    public function after(Closure $closure): self
    {
        if ($this->description === null) {
            throw new TestDescriptionMissing($this->filename);
        }

        $description = $this->describing === []
            ? $this->description
            : Str::describe($this->describing, $this->description);

        $filename = $this->filename;

        $when = function () use ($closure, $filename, $description): void {
            if ($this::$__filename !== $filename) { // @phpstan-ignore-line
                return;
            }

            if ($this->__description !== $description) { // @phpstan-ignore-line
                return;
            }

            if ($this->__ran !== true) { // @phpstan-ignore-line
                return;
            }

            $closure->call($this);
        };

        new AfterEachCall($this->testSuite, $this->filename, $when->bindTo(new \stdClass));

        return $this;
    }

    /**
     * Asserts that the test fails with the given message.
     */
    public function fails(?string $message = null): self
    {
        return $this->throws(AssertionFailedError::class, $message);
    }

    /**
     * Asserts that the test throws the given `$exceptionClass` when called.
     */
    public function throws(string|int $exception, ?string $exceptionMessage = null, ?int $exceptionCode = null): self
    {
        if (is_int($exception)) {
            $exceptionCode = $exception;
        } elseif (class_exists($exception)) {
            $this->testCaseMethod
                ->proxies
                ->add(Backtrace::file(), Backtrace::line(), 'expectException', [$exception]);
        } else {
            $exceptionMessage = $exception;
        }

        if (is_string($exceptionMessage)) {
            $this->testCaseMethod
                ->proxies
                ->add(Backtrace::file(), Backtrace::line(), 'expectExceptionMessage', [$exceptionMessage]);
        }

        if (is_int($exceptionCode)) {
            $this->testCaseMethod
                ->proxies
                ->add(Backtrace::file(), Backtrace::line(), 'expectExceptionCode', [$exceptionCode]);
        }

        return $this;
    }

    /**
     * Asserts that the test throws the given `$exceptionClass` when called if the given condition is true.
     *
     * @param  (callable(): bool)|bool  $condition
     */
    public function throwsIf(callable|bool $condition, string|int $exception, ?string $exceptionMessage = null, ?int $exceptionCode = null): self
    {
        $condition = is_callable($condition)
            ? $condition
            : static fn (): bool => $condition;

        if ($condition()) {
            return $this->throws($exception, $exceptionMessage, $exceptionCode);
        }

        return $this;
    }

    /**
     * Asserts that the test throws the given `$exceptionClass` when called if the given condition is false.
     *
     * @param  (callable(): bool)|bool  $condition
     */
    public function throwsUnless(callable|bool $condition, string|int $exception, ?string $exceptionMessage = null, ?int $exceptionCode = null): self
    {
        $condition = is_callable($condition)
            ? $condition
            : static fn (): bool => $condition;

        if (! $condition()) {
            return $this->throws($exception, $exceptionMessage, $exceptionCode);
        }

        return $this;
    }

    /**
     * Runs the current test multiple times with
     * each item of the given `iterable`.
     *
     * @param  array<\Closure|iterable<int|string, mixed>|string>  $data
     */
    public function with(Closure|iterable|string ...$data): self
    {
        foreach ($data as $dataset) {
            $this->testCaseMethod->datasets[] = $dataset;
        }

        return $this;
    }

    /**
     * Sets the test depends.
     */
    public function depends(string ...$depends): self
    {
        foreach ($depends as $depend) {
            $this->testCaseMethod->depends[] = $depend;
        }

        return $this;
    }

    /**
     * Sets the test group(s).
     */
    public function group(string ...$groups): self
    {
        foreach ($groups as $group) {
            $this->testCaseMethod->attributes[] = new Attribute(
                \PHPUnit\Framework\Attributes\Group::class,
                [$group],
            );
        }

        return $this;
    }

    /**
     * Filters the test suite by "only" tests.
     */
    public function only(): self
    {
        Only::enable($this, ...func_get_args()); // @phpstan-ignore-line

        return $this;
    }

    /**
     * Skips the current test.
     */
    public function skip(Closure|bool|string $conditionOrMessage = true, string $message = ''): self
    {
        $condition = is_string($conditionOrMessage)
            ? NullClosure::create()
            : $conditionOrMessage;

        $condition = is_callable($condition)
            ? $condition
            : fn (): bool => $condition;

        $message = is_string($conditionOrMessage)
            ? $conditionOrMessage
            : $message;

        /** @var callable(): bool $condition */
        $condition = $condition->bindTo(null);

        $this->testCaseMethod
            ->chains
            ->addWhen($condition, $this->filename, Backtrace::line(), 'markTestSkipped', [$message]);

        return $this;
    }

    /**
     * Skips the current test on the given PHP version.
     */
    public function skipOnPhp(string $version): self
    {
        if (mb_strlen($version) < 2) {
            throw new InvalidArgumentException('The version must start with [<] or [>].');
        }

        if (str_starts_with($version, '>=') || str_starts_with($version, '<=')) {
            $operator = substr($version, 0, 2);
            $version = substr($version, 2);
        } elseif (str_starts_with($version, '>') || str_starts_with($version, '<')) {
            $operator = $version[0];
            $version = substr($version, 1);
            // ensure starts with number:
        } elseif (is_numeric($version[0])) {
            $operator = '==';
        } else {
            throw new InvalidArgumentException('The version must start with [<, >, <=, >=] or a number.');
        }

        return $this->skip(version_compare(PHP_VERSION, $version, $operator), sprintf('This test is skipped on PHP [%s%s].', $operator, $version));
    }

    /**
     * Skips the current test if the given test is running on Windows.
     */
    public function skipOnWindows(): self
    {
        return $this->skipOnOs('Windows', 'This test is skipped on [Windows].');
    }

    /**
     * Skips the current test if the given test is running on Mac OS.
     */
    public function skipOnMac(): self
    {
        return $this->skipOnOs('Darwin', 'This test is skipped on [Mac].');
    }

    /**
     * Skips the current test if the given test is running on Linux.
     */
    public function skipOnLinux(): self
    {
        return $this->skipOnOs('Linux', 'This test is skipped on [Linux].');
    }

    /**
     * Skips the current test if the given test is running on the given operating systems.
     */
    private function skipOnOs(string $osFamily, string $message): self
    {
        return $osFamily === PHP_OS_FAMILY
            ? $this->skip($message)
            : $this;
    }

    /**
     * Skips the current test unless the given test is running on Windows.
     */
    public function onlyOnWindows(): self
    {
        return $this->skipOnMac()->skipOnLinux();
    }

    /**
     * Skips the current test unless the given test is running on Mac.
     */
    public function onlyOnMac(): self
    {
        return $this->skipOnWindows()->skipOnLinux();
    }

    /**
     * Skips the current test unless the given test is running on Linux.
     */
    public function onlyOnLinux(): self
    {
        return $this->skipOnWindows()->skipOnMac();
    }

    /**
     * Repeats the current test the given number of times.
     */
    public function repeat(int $times): self
    {
        if ($times < 1) {
            throw new InvalidArgumentException('The number of repetitions must be greater than 0.');
        }

        $this->testCaseMethod->repetitions = $times;

        return $this;
    }

    /**
     * Marks the test as "todo".
     */
    public function todo(// @phpstan-ignore-line
        array|string|null $note = null,
        array|string|null $assignee = null,
        array|string|int|null $issue = null,
        array|string|int|null $pr = null,
    ): self {
        $this->skip('__TODO__');

        $this->testCaseMethod->todo = true;

        if ($issue !== null) {
            $this->issue($issue);
        }

        if ($pr !== null) {
            $this->pr($pr);
        }

        if ($assignee !== null) {
            $this->assignee($assignee);
        }

        if ($note !== null) {
            $this->note($note);
        }

        return $this;
    }

    /**
     * Sets the test as "work in progress".
     */
    public function wip(// @phpstan-ignore-line
        array|string|null $note = null,
        array|string|null $assignee = null,
        array|string|int|null $issue = null,
        array|string|int|null $pr = null,
    ): self {
        if ($issue !== null) {
            $this->issue($issue);
        }

        if ($pr !== null) {
            $this->pr($pr);
        }

        if ($assignee !== null) {
            $this->assignee($assignee);
        }

        if ($note !== null) {
            $this->note($note);
        }

        return $this;
    }

    /**
     * Sets the test as "done".
     */
    public function done(// @phpstan-ignore-line
        array|string|null $note = null,
        array|string|null $assignee = null,
        array|string|int|null $issue = null,
        array|string|int|null $pr = null,
    ): self {
        if ($issue !== null) {
            $this->issue($issue);
        }

        if ($pr !== null) {
            $this->pr($pr);
        }

        if ($assignee !== null) {
            $this->assignee($assignee);
        }

        if ($note !== null) {
            $this->note($note);
        }

        return $this;
    }

    /**
     * Associates the test with the given issue(s).
     *
     * @param  array<int, string|int>|string|int  $number
     */
    public function issue(array|string|int $number): self
    {
        $number = is_array($number) ? $number : [$number];

        $number = array_map(fn (string|int $number): int => (int) ltrim((string) $number, '#'), $number);

        $this->testCaseMethod->issues = array_merge($this->testCaseMethod->issues, $number);

        return $this;
    }

    /**
     * Associates the test with the given ticket(s). (Alias for `issue`)
     *
     * @param  array<int, string|int>|string|int  $number
     */
    public function ticket(array|string|int $number): self
    {
        return $this->issue($number);
    }

    /**
     * Sets the test assignee(s).
     *
     * @param  array<int, string>|string  $assignee
     */
    public function assignee(array|string $assignee): self
    {
        $assignees = is_array($assignee) ? $assignee : [$assignee];

        $this->testCaseMethod->assignees = array_unique(array_merge($this->testCaseMethod->assignees, $assignees));

        return $this;
    }

    /**
     * Associates the test with the given pull request(s).
     *
     * @param  array<int, string|int>|string|int  $number
     */
    public function pr(array|string|int $number): self
    {
        $number = is_array($number) ? $number : [$number];

        $number = array_map(fn (string|int $number): int => (int) ltrim((string) $number, '#'), $number);

        $this->testCaseMethod->prs = array_unique(array_merge($this->testCaseMethod->prs, $number));

        return $this;
    }

    /**
     * Adds a note to the test.
     *
     * @param  array<int, string>|string  $note
     */
    public function note(array|string $note): self
    {
        $notes = is_array($note) ? $note : [$note];

        $this->testCaseMethod->notes = array_unique(array_merge($this->testCaseMethod->notes, $notes));

        return $this;
    }

    /**
     * Sets the covered classes or methods.
     *
     * @param  array<int, string>|string  $classesOrFunctions
     */
    public function covers(array|string ...$classesOrFunctions): self
    {
        /** @var array<int, string> $classesOrFunctions */
        $classesOrFunctions = array_reduce($classesOrFunctions, fn ($carry, $item): array => is_array($item) ? array_merge($carry, $item) : array_merge($carry, [$item]), []); // @pest-ignore-type

        foreach ($classesOrFunctions as $classOrFunction) {
            $isClass = class_exists($classOrFunction) || interface_exists($classOrFunction) || enum_exists($classOrFunction);
            $isTrait = trait_exists($classOrFunction);
            $isFunction = function_exists($classOrFunction);

            if (! $isClass && ! $isTrait && ! $isFunction) {
                throw new InvalidArgumentException(sprintf('No class, trait or method named "%s" has been found.', $classOrFunction));
            }

            if ($isClass) {
                $this->coversClass($classOrFunction);
            } elseif ($isTrait) {
                $this->coversTrait($classOrFunction);
            } else {
                $this->coversFunction($classOrFunction);
            }
        }

        return $this;
    }

    /**
     * Sets the covered classes.
     */
    public function coversClass(string ...$classes): self
    {
        foreach ($classes as $class) {
            $this->testCaseFactoryAttributes[] = new Attribute(
                \PHPUnit\Framework\Attributes\CoversClass::class,
                [$class],
            );
        }

        /** @var ConfigurationRepository $configurationRepository */
        $configurationRepository = Container::getInstance()->get(ConfigurationRepository::class);
        $paths = $configurationRepository->cliConfiguration->toArray()['paths'] ?? false;

        if (! is_array($paths)) {
            $configurationRepository->globalConfiguration('default')->class(...$classes); // @phpstan-ignore-line
        }

        return $this;
    }

    /**
     * Sets the covered classes.
     */
    public function coversTrait(string ...$traits): self
    {
        foreach ($traits as $trait) {
            $this->testCaseFactoryAttributes[] = new Attribute(
                \PHPUnit\Framework\Attributes\CoversTrait::class,
                [$trait],
            );
        }

        /** @var ConfigurationRepository $configurationRepository */
        $configurationRepository = Container::getInstance()->get(ConfigurationRepository::class);
        $paths = $configurationRepository->cliConfiguration->toArray()['paths'] ?? false;

        if (! is_array($paths)) {
            $configurationRepository->globalConfiguration('default')->class(...$traits); // @phpstan-ignore-line
        }

        return $this;
    }

    /**
     * Sets the covered functions.
     */
    public function coversFunction(string ...$functions): self
    {
        foreach ($functions as $function) {
            $this->testCaseFactoryAttributes[] = new Attribute(
                \PHPUnit\Framework\Attributes\CoversFunction::class,
                [$function],
            );
        }

        return $this;
    }

    /**
     * Sets that the current test covers nothing.
     */
    public function coversNothing(): self
    {
        $this->testCaseMethod->attributes[] = new Attribute(
            \PHPUnit\Framework\Attributes\CoversNothing::class,
            [],
        );

        return $this;
    }

    /**
     * Informs the test runner that no expectations happen in this test,
     * and its purpose is simply to check whether the given code can
     * be executed without throwing exceptions.
     */
    public function throwsNoExceptions(): self
    {
        $this->testCaseMethod->proxies->add(Backtrace::file(), Backtrace::line(), 'expectNotToPerformAssertions', []);

        return $this;
    }

    /**
     * Saves the property accessors to be used on the target.
     */
    public function __get(string $name): self
    {
        return $this->addChain(Backtrace::file(), Backtrace::line(), $name);
    }

    /**
     * Saves the calls to be used on the target.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): self
    {
        return $this->addChain(Backtrace::file(), Backtrace::line(), $name, $arguments);
    }

    /**
     * Add a chain to the test case factory. Omitting the arguments will treat it as a property accessor.
     *
     * @param  array<int, mixed>|null  $arguments
     */
    private function addChain(string $file, int $line, string $name, ?array $arguments = null): self
    {
        $exporter = Exporter::default();

        $this->testCaseMethod
            ->chains
            ->add($file, $line, $name, $arguments);

        if ($this->descriptionLess) {
            Exporter::default();

            if ($this->description !== null) {
                $this->description .= ' → ';
            }

            $this->description .= $arguments === null
                ? $name
                : sprintf('%s %s', $name, $exporter->shortenedRecursiveExport($arguments));
        }

        return $this;
    }

    /**
     * Creates the Call.
     */
    public function __destruct()
    {
        if ($this->description === null) {
            throw new TestDescriptionMissing($this->filename);
        }

        if ($this->describing !== []) {
            $this->testCaseMethod->describing = $this->describing;
            $this->testCaseMethod->description = Str::describe($this->describing, $this->description);
        } else {
            $this->testCaseMethod->description = $this->description;
        }

        $this->testSuite->tests->set($this->testCaseMethod);

        if (! is_null($testCase = $this->testSuite->tests->get($this->filename))) {
            $testCase->attributes = array_merge($testCase->attributes, $this->testCaseFactoryAttributes);
        }
    }
}
