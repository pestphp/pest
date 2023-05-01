<?php

declare(strict_types=1);

namespace Pest\PendingCalls;

use Closure;
use Pest\Support\Backtrace;
use Pest\Support\ChainableClosure;
use Pest\Support\HigherOrderMessageCollection;
use Pest\Support\NullClosure;
use Pest\TestSuite;

/**
 * @internal
 */
final class BeforeEachCall
{
    /**
     * Holds the before each closure.
     */
    private readonly Closure $closure;

    /**
     * The test call proxies.
     */
    private readonly HigherOrderMessageCollection $testCallProxies;

    /**
     * The test case proxies.
     */
    private readonly HigherOrderMessageCollection $testCaseProxies;

    /**
     * Creates a new Pending Call.
     */
    public function __construct(
        private readonly TestSuite $testSuite,
        private readonly string $filename,
        Closure $closure = null
    ) {
        $this->closure = $closure instanceof Closure ? $closure : NullClosure::create();

        $this->testCallProxies = new HigherOrderMessageCollection();
        $this->testCaseProxies = new HigherOrderMessageCollection();
    }

    /**
     * Creates the Call.
     */
    public function __destruct()
    {
        $testCaseProxies = $this->testCaseProxies;

        $this->testSuite->beforeEach->set(
            $this->filename,
            function (TestCall $testCall): void {
                $this->testCallProxies->chain($testCall);
            },
            ChainableClosure::from(function () use ($testCaseProxies): void {
                $testCaseProxies->chain($this);
            }, $this->closure),
        );
    }

    /**
     * Saves the calls to be used on the target.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): self
    {
        if (method_exists(TestCall::class, $name)) {
            $this->testCallProxies->add(Backtrace::file(), Backtrace::line(), $name, $arguments);

            return $this;
        }

        $this->testCaseProxies
            ->add(Backtrace::file(), Backtrace::line(), $name, $arguments);

        return $this;
    }
}
