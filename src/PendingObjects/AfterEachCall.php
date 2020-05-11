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
     * Holds the test suite.
     *
     * @var TestSuite
     */
    private $testSuite;

    /**
     * Holds the filename.
     *
     * @var string
     */
    private $filename;

    /**
     * Holds the before each closure.
     *
     * @var Closure
     */
    private $closure;

    /**
     * Holds calls that should be proxied.
     *
     * @var HigherOrderMessageCollection
     */
    private $proxies;

    /**
     * Creates a new instance of before each call.
     */
    public function __construct(TestSuite $testSuite, string $filename, Closure $closure = null)
    {
        $this->testSuite = $testSuite;
        $this->filename  = $filename;
        $this->closure   = $closure instanceof Closure ? $closure : NullClosure::create();

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
