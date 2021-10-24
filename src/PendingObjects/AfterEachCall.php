<?php

declare(strict_types=1);

namespace Pest\PendingObjects;

use Closure;
use Pest\Support\Backtrace;
use Pest\Support\ChainableClosure;
use Pest\Support\HigherOrderMessageCollection;
use Pest\Support\NullClosure;
use Pest\TestSuite;

/**
 * @internal
 */
final class AfterEachCall
{
    /**
     * Holds the before each closure.
     */
    private Closure $closure;

    /**
     * Holds calls that should be proxied.
     */
    private HigherOrderMessageCollection $proxies;

    /**
     * Creates a new instance of before each call.
     */
    public function __construct(
        private TestSuite $testSuite,
        private string $filename, Closure $closure = null
    ) {
        $this->closure = $closure instanceof Closure ? $closure : NullClosure::create();

        $this->proxies = new HigherOrderMessageCollection();
    }

    /**
     * Dispatch the creation of each call.
     */
    public function __destruct()
    {
        $proxies = $this->proxies;

        $this->testSuite->afterEach->set(
            $this->filename,
            ChainableClosure::from(function () use ($proxies): void {
                $proxies->chain($this);
            }, $this->closure)
        );
    }

    /**
     * Saves the calls to be used on the target.
     *
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): self
    {
        $this->proxies
            ->add(Backtrace::file(), Backtrace::line(), $name, $arguments);

        return $this;
    }
}
