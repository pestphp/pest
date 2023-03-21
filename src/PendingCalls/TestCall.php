<?php

declare(strict_types=1);

namespace Pest\PendingCalls;

use Closure;
use InvalidArgumentException;
use Pest\Factories\Covers\CoversClass;
use Pest\Factories\Covers\CoversFunction;
use Pest\Factories\Covers\CoversNothing;
use Pest\Factories\TestCaseMethodFactory;
use Pest\Plugins\Only;
use Pest\Support\Backtrace;
use Pest\Support\Exporter;
use Pest\Support\HigherOrderCallables;
use Pest\Support\NullClosure;
use Pest\TestSuite;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @mixin HigherOrderCallables|TestCase
 */
final class TestCall
{
    /**
     * The Test Case Factory.
     */
    private readonly TestCaseMethodFactory $testCaseMethod;

    /**
     * If test call is descriptionLess.
     */
    private readonly bool $descriptionLess;

    /**
     * Creates a new Pending Call.
     */
    public function __construct(
        private readonly TestSuite $testSuite,
        string $filename,
        string $description = null,
        Closure $closure = null
    ) {
        $this->testCaseMethod = new TestCaseMethodFactory($filename, $description, $closure);
        $this->descriptionLess = $description === null;
    }

    /**
     * Asserts that the test throws the given `$exceptionClass` when called.
     */
    public function throws(string|int $exception, string $exceptionMessage = null, int $exceptionCode = null): self
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
     * @param (callable(): bool)|bool $condition
     */
    public function throwsIf(callable|bool $condition, string|int $exception, string $exceptionMessage = null, int $exceptionCode = null): self
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
            $this->testCaseMethod->groups[] = $group;
        }

        return $this;
    }

    /**
     * Filters the test suite by "only" tests.
     */
    public function only(): self
    {
        Only::enable($this);

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
            ->addWhen($condition, Backtrace::file(), Backtrace::line(), 'markTestSkipped', [$message]);

        return $this;
    }

    /**
     * Sets the test as "todo".
     */
    public function todo(): self
    {
        $this->skip('__TODO__');

        $this->testCaseMethod->todo = true;

        return $this;
    }

    /**
     * Sets the covered classes or methods.
     */
    public function covers(string ...$classesOrFunctions): self
    {
        foreach ($classesOrFunctions as $classOrFunction) {
            $isClass = class_exists($classOrFunction);
            $isMethod = function_exists($classOrFunction);

            if (! $isClass && ! $isMethod) {
                throw new InvalidArgumentException(sprintf('No class or method named "%s" has been found.', $classOrFunction));
            }

            if ($isClass) {
                $this->coversClass($classOrFunction);
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
            $this->testCaseMethod->covers[] = new CoversClass($class);
        }

        return $this;
    }

    /**
     * Sets the covered functions.
     */
    public function coversFunction(string ...$functions): self
    {
        foreach ($functions as $function) {
            $this->testCaseMethod->covers[] = new CoversFunction($function);
        }

        return $this;
    }

    /**
     * Sets that the current test covers nothing.
     */
    public function coversNothing(): self
    {
        $this->testCaseMethod->covers = [new CoversNothing()];

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
    private function addChain(string $file, int $line, string $name, array $arguments = null): self
    {
        $exporter = Exporter::default();
        $this->testCaseMethod
            ->chains
            ->add($file, $line, $name, $arguments);

        if ($this->descriptionLess) {
            Exporter::default();
            if ($this->testCaseMethod->description !== null) {
                $this->testCaseMethod->description .= ' → ';
            }
            $this->testCaseMethod->description .= $arguments === null
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
        $this->testSuite->tests->set($this->testCaseMethod);
    }
}
