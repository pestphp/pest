<?php

declare(strict_types=1);

namespace Pest\PendingCalls;

use Closure;
use Pest\PendingCalls\Concerns\Describable;
use Pest\Support\Arr;
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

    private readonly Closure $closure;

    private readonly HigherOrderMessageCollection $proxies;

    public function __construct(
        private readonly TestSuite $testSuite,
        private readonly string $filename,
        ?Closure $closure = null
    ) {
        $this->closure = $closure instanceof Closure ? $closure : NullClosure::create();

        $this->proxies = new HigherOrderMessageCollection;

        $this->describing = DescribeCall::describing();
    }

    public function __destruct()
    {
        $describing = $this->describing;

        $proxies = $this->proxies;

        $afterEachTestCase = ChainableClosure::boundWhen(
            fn (): bool => $describing === [] || in_array(Arr::last($describing), $this->__describing, true),
            ChainableClosure::bound(fn () => $proxies->chain($this), $this->closure)->bindTo($this, self::class),
        )->bindTo($this, self::class);

        assert($afterEachTestCase instanceof Closure);

        $this->testSuite->afterEach->set(
            $this->filename,
            $this,
            $afterEachTestCase,
        );
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): self
    {
        $this->proxies
            ->add(Backtrace::file(), Backtrace::line(), $name, $arguments);

        return $this;
    }
}
