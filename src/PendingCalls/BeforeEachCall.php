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
    private readonly \Closure $closure;

    /**
     * The calls that should be proxied.
     */
    private readonly HigherOrderMessageCollection $proxies;

    /**
     * Creates a new Pending Call.
     */
    public function __construct(
        private readonly TestSuite $testSuite,
        private readonly string $filename,
        Closure $closure = null
    ) {
        $this->closure = $closure instanceof Closure ? $closure : NullClosure::create();

        $this->proxies = new HigherOrderMessageCollection();
    }

    /**
     * Creates the Call.
     */
    public function __destruct()
    {
        $proxies = $this->proxies;

        $this->testSuite->beforeEach->set(
            $this->filename,
            ChainableClosure::from(function () use ($proxies): void {
                $proxies->chain($this);
            }, $this->closure)
        );
    }

    /**
     * Saves the calls to be used on the target.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): self
    {
        $this->proxies
            ->add(Backtrace::file(), Backtrace::line(), $name, $arguments);

        return $this;
    }
}
