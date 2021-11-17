<?php

declare(strict_types=1);

namespace Pest\PendingObjects;

use Closure;
use Pest\Factories\TestCaseFactory;
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
     * Holds the test suite.
     *
     * @readonly
     *
     * @var TestSuite
     */
    private $testSuite;

    /**
     * Holds the test case factory.
     *
     * @readonly
     *
     * @var TestCaseFactory
     */
    private $testCaseFactory;

    /**
     * If test call is descriptionLess.
     *
     * @readonly
     *
     * @var bool
     */
    private $descriptionLess = false;

    /**
     * Creates a new instance of a pending test call.
     */
    public function __construct(TestSuite $testSuite, string $filename, string $description = null, Closure $closure = null)
    {
        $this->testCaseFactory = new TestCaseFactory($filename, $description, $closure);
        $this->testSuite       = $testSuite;
        $this->descriptionLess = $description === null;
    }

    /**
     * Asserts that the test throws the given `$exceptionClass` when called.
     */
    public function throws(string $exception, string $exceptionMessage = null): TestCall
    {
        if (class_exists($exception)) {
            $this->testCaseFactory
                ->proxies
                ->add(Backtrace::file(), Backtrace::line(), 'expectException', [$exception]);
        } else {
            $exceptionMessage = $exception;
        }

        if (is_string($exceptionMessage)) {
            $this->testCaseFactory
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
    public function throwsIf($condition, string $exception, string $exceptionMessage = null): TestCall
    {
        $condition = is_callable($condition)
            ? $condition
            : static function () use ($condition): bool {
                return (bool) $condition; // @phpstan-ignore-line
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
     * @param array<\Closure|iterable<int|string, mixed>|string> $data
     */
    public function with(...$data): TestCall
    {
        foreach ($data as $dataset) {
            $this->testCaseFactory->datasets[] = $dataset;
        }

        return $this;
    }

    /**
     * Sets the test depends.
     */
    public function depends(string ...$tests): TestCall
    {
        $this->testCaseFactory
            ->factoryProxies
            ->add(Backtrace::file(), Backtrace::line(), 'addDependencies', [$tests]);

        return $this;
    }

    /**
     * Makes the test suite only this test case.
     */
    public function only(): TestCall
    {
        $this->testCaseFactory->only = true;

        return $this;
    }

    /**
     * Sets the test group(s).
     */
    public function group(string ...$groups): TestCall
    {
        $this->testCaseFactory
            ->factoryProxies
            ->add(Backtrace::file(), Backtrace::line(), 'addGroups', [$groups]);

        return $this;
    }

    /**
     * Skips the current test.
     *
     * @param Closure|bool|string $conditionOrMessage
     */
    public function skip($conditionOrMessage = true, string $message = ''): TestCall
    {
        $condition = is_string($conditionOrMessage)
            ? NullClosure::create()
            : $conditionOrMessage;

        $condition = is_callable($condition)
            ? $condition
            : function () use ($condition) {
                return $condition;
            };

        $message = is_string($conditionOrMessage)
            ? $conditionOrMessage
            : $message;

        /** @var callable(): bool $condition */
        $condition = $condition->bindTo(null);

        $this->testCaseFactory
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
        $this->testCaseFactory
            ->chains
            ->add(Backtrace::file(), Backtrace::line(), $name, $arguments);

        if ($this->descriptionLess) {
            $exporter = new Exporter();
            if ($this->testCaseFactory->description !== null) {
                $this->testCaseFactory->description .= ' → ';
            }
            $this->testCaseFactory->description .= $arguments === null
                ? $name
                : sprintf('%s %s', $name, $exporter->shortenedRecursiveExport($arguments));
        }

        return $this;
    }

    /**
     * Adds the current test case factory
     * to the tests repository.
     */
    public function __destruct()
    {
        $this->testSuite->tests->set($this->testCaseFactory);
    }
}
