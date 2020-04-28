<?php

declare(strict_types=1);

namespace Pest\PendingObjects;

use Closure;
use Pest\Factories\TestCaseFactory;
use Pest\Support\Backtrace;
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
     * Sets the test groups.
     *
     * @var array<int, string>
     */
    public function groups(array $groups): TestCall
    {
        $this->testCaseFactory
             ->factoryProxies
             ->add(Backtrace::file(), Backtrace::line(), 'setGroups', [$groups]);

        return $this;
    }

    /**
     * Sets the test groups.
     */
    public function group(string $group): TestCall
    {
        return $this->groups([$group]);
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
