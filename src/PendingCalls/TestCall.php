<?php

declare(strict_types=1);

namespace Pest\PendingCalls;

use Closure;
use Pest\Dataset;
use Pest\Factories\TestCaseMethodFactory;
use Pest\Support\Backtrace;
use Pest\Support\HigherOrderCallables;
use Pest\Support\NullClosure;
use Pest\TestSuite;
use SebastianBergmann\Exporter\Exporter;

/**
 * @internal
 *
 * @mixin HigherOrderCallables
 */
final class TestCall
{
    /**
     * The Test Case Factory.
     */
    private TestCaseMethodFactory $testCaseMethod;

    /**
     * If test call is descriptionLess.
     */
    private bool $descriptionLess;

    /**
     * Creates a new Pending Call.
     */
    public function __construct(
        private TestSuite $testSuite,
        string $filename,
        string $description = null,
        Closure $closure = null
    ) {
        $this->testCaseMethod  = new TestCaseMethodFactory($filename, $description, $closure);
        $this->descriptionLess = $description === null;
    }

    /**
     * Asserts that the test throws the given `$exceptionClass` when called.
     */
    public function throws(string $exception, string $exceptionMessage = null): TestCall
    {
        if (class_exists($exception)) {
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

        return $this;
    }

    /**
     * Asserts that the test throws the given `$exceptionClass` when called if the given condition is true.
     *
     * @param (callable(): bool)|bool $condition
     */
    public function throwsIf(callable|bool $condition, string $exception, string $exceptionMessage = null): TestCall
    {
        $condition = is_callable($condition)
            ? $condition
            : static function () use ($condition): bool {
                return $condition;
            };

        if ($condition()) {
            return $this->throws($exception, $exceptionMessage);
        }

        return $this;
    }

    /**
     * Runs the current test multiple times with
     * each item of the given `iterable`.
     *
     * @param Closure|iterable<int|string, mixed>|string $dataset
     */
    public function with(Closure|iterable|string $dataset, mixed ...$parameters): TestCall
    {
        $this->testCaseMethod->datasets[] = new Dataset($dataset, $parameters);

        return $this;
    }

    /**
     * Sets the test depends.
     */
    public function depends(string ...$depends): TestCall
    {
        foreach ($depends as $depend) {
            $this->testCaseMethod->depends[] = $depend;
        }

        return $this;
    }

    /**
     * Makes the test suite only this test case.
     */
    public function only(): TestCall
    {
        $this->testCaseMethod->only = true;

        return $this;
    }

    /**
     * Sets the test group(s).
     */
    public function group(string ...$groups): TestCall
    {
        foreach ($groups as $group) {
            $this->testCaseMethod->groups[] = $group;
        }

        return $this;
    }

    /**
     * Skips the current test.
     */
    public function skip(Closure|bool|string $conditionOrMessage = true, string $message = ''): TestCall
    {
        $condition = is_string($conditionOrMessage)
            ? NullClosure::create()
            : $conditionOrMessage;

        $condition = is_callable($condition)
            ? $condition
            : fn () => $condition;

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
     * Saves the property accessors to be used on the target.
     */
    public function __get(string $name): self
    {
        return $this->addChain($name);
    }

    /**
     * Saves the calls to be used on the target.
     *
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): self
    {
        return $this->addChain($name, $arguments);
    }

    /**
     * Add a chain to the test case factory. Omitting the arguments will treat it as a property accessor.
     *
     * @param array<int, mixed>|null $arguments
     */
    private function addChain(string $name, array $arguments = null): self
    {
        $this->testCaseMethod
            ->chains
            ->add(Backtrace::file(), Backtrace::line(), $name, $arguments);

        if ($this->descriptionLess) {
            $exporter = new Exporter();
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
