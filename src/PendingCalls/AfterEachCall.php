<?php

declare(strict_types=1);

namespace Pest\PendingCalls;

use Closure;
use Pest\PendingCalls\Concerns\Describable;
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
    use Describable;

    /**
     * The "afterEach" closure.
     */
    private readonly Closure $closure;

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
        ?Closure $closure = null
    ) {
        $this->closure = $closure instanceof Closure ? $closure : NullClosure::create();

        $this->proxies = new HigherOrderMessageCollection;

        $this->describing = DescribeCall::describing();
    }

    /**
     * Creates the Call.
     */
    public function __destruct()
    {
        $describing = $this->describing;

        $proxies = $this->proxies;

        $afterEachTestCase = ChainableClosure::boundWhen(
            fn (): bool => is_null($describing) || $this->__describing === $describing, // @phpstan-ignore-line
            ChainableClosure::bound(fn () => $proxies->chain($this), $this->closure)->bindTo($this, self::class), // @phpstan-ignore-line
        )->bindTo($this, self::class);

        assert($afterEachTestCase instanceof Closure);

        $this->testSuite->afterEach->set(
            $this->filename,
            $this,
            $afterEachTestCase,
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
