<?php

declare(strict_types=1);

namespace Pest\PendingObjects;

use Closure;
use Pest\Factories\TestCaseFactory;
use Pest\Support\Backtrace;
use Pest\Support\NullClosure;
use Pest\TestSuite;

/**
 * @internal
 */
final class TestCall
{
    /**
     * Holds the test case factory.
     *
     * @readonly
     *
     * @var TestCaseFactory
     */
    private $testCaseFactory;

    /**
     * Creates a new instance of a pending test call.
     */
    public function __construct(TestSuite $testSuite, string $filename, string $description, Closure $closure = null)
    {
        $this->testCaseFactory = new TestCaseFactory($filename, $description, $closure);

        $testSuite->tests->set($this->testCaseFactory);
    }

    /**
     * Asserts that the test throws the given `$exceptionClass` when called.
     */
    public function throws(string $exceptionClass, string $exceptionMessage = null): TestCall
    {
        $this->testCaseFactory
             ->proxies
             ->add(Backtrace::file(), Backtrace::line(), 'expectException', [$exceptionClass]);

        if (is_string($exceptionMessage)) {
            $this->testCaseFactory
                 ->proxies
                 ->add(Backtrace::file(), Backtrace::line(), 'expectExceptionMessage', [$exceptionMessage]);
        }

        return $this;
    }

    /**
     * Runs the current test multiple times with
     * each item of the given `iterable`.
     *
     * @param \Closure|iterable|string $data
     */
    public function with($data): TestCall
    {
        $this->testCaseFactory->dataset = $data;

        return $this;
    }

    /**
     * Sets the test groups(s).
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
            : function () use ($condition) { /* @phpstan-ignore-line */
                return $condition;
            };

        $message = is_string($conditionOrMessage)
            ? $conditionOrMessage
            : $message;

        if ($condition() !== false) {
            $this->testCaseFactory
                 ->chains
                 ->add(Backtrace::file(), Backtrace::line(), 'markTestSkipped', [$message]);
        }

        return $this;
    }

    /**
     * Saves the calls to be used on the target.
     */
    public function __call(string $name, array $arguments): self
    {
        $this->testCaseFactory
             ->chains
             ->add(Backtrace::file(), Backtrace::line(), $name, $arguments);

        return $this;
    }
}
