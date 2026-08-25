<?php

declare(strict_types=1);

namespace Pest\PendingCalls;

use Closure;
use Pest\Exceptions\AfterBeforeTestFunction;
use Pest\PendingCalls\Concerns\Describable;
use Pest\Support\Arr;
use Pest\Support\Backtrace;
use Pest\Support\ChainableClosure;
use Pest\Support\HigherOrderMessageCollection;
use Pest\Support\NullClosure;
use Pest\TestSuite;

/**
 * @internal
 *
 * @mixin TestCall
 */
final class BeforeEachCall
{
    use Describable;

    private readonly Closure $closure;

    private readonly HigherOrderMessageCollection $testCallProxies;

    private readonly HigherOrderMessageCollection $testCaseProxies;

    public function __construct(
        public readonly TestSuite $testSuite,
        private readonly string $filename,
        ?Closure $closure = null
    ) {
        $this->closure = $closure instanceof Closure ? $closure : NullClosure::create();

        $this->testCallProxies = new HigherOrderMessageCollection;
        $this->testCaseProxies = new HigherOrderMessageCollection;

        $this->describing = DescribeCall::describing();
    }

    public function __destruct()
    {
        $describing = $this->describing;
        $testCaseProxies = $this->testCaseProxies;

        $beforeEachTestCall = function (TestCall $testCall) use ($describing): void {

            if ($this->describing !== []) {
                if (Arr::last($describing) !== Arr::last($this->describing)) {
                    return;
                }

                if (! in_array(Arr::last($describing), $testCall->describing, true)) {
                    return;
                }
            }

            $this->testCallProxies->chain($testCall);
        };

        $beforeEachTestCase = ChainableClosure::boundWhen(
            fn (): bool => $describing === [] || in_array(Arr::last($describing), $this->__describing, true),
            ChainableClosure::bound(fn () => $testCaseProxies->chain($this), $this->closure)->bindTo($this, self::class),
        )->bindTo($this, self::class);

        assert($beforeEachTestCase instanceof Closure);

        $this->testSuite->beforeEach->set(
            $this->filename,
            $this,
            $beforeEachTestCall,
            $beforeEachTestCase,
        );
    }

    public function after(Closure $closure): self
    {
        if ($this->describing === []) {
            throw new AfterBeforeTestFunction($this->filename);
        }

        return $this->__call('after', [$closure]);
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): self
    {
        if (method_exists(TestCall::class, $name)) {
            $this->testCallProxies
                ->add(Backtrace::file(), Backtrace::line(), $name, $arguments);

            return $this;
        }

        $this->testCaseProxies
            ->add(Backtrace::file(), Backtrace::line(), $name, $arguments);

        return $this;
    }
}
