<?php

declare(strict_types=1);

namespace Pest\Repositories;

use Closure;
use Mockery;
use Pest\PendingCalls\AfterEachCall;
use Pest\Support\ChainableClosure;
use Pest\Support\NullClosure;

/**
 * @internal
 */
final class AfterEachRepository
{
    /**
     * @var array<string, Closure>
     */
    private array $state = [];

    /**
     * Sets a after each closure.
     */
    public function set(string $filename, AfterEachCall $afterEachCall, Closure $afterEachTestCase): void
    {
        if (array_key_exists($filename, $this->state)) {
            $fromAfterEachTestCase = $this->state[$filename];

            $afterEachTestCase = ChainableClosure::bound($fromAfterEachTestCase, $afterEachTestCase)
                ->bindTo($afterEachCall, $afterEachCall::class);
        }

        assert($afterEachTestCase instanceof Closure);

        $this->state[$filename] = $afterEachTestCase;
    }

    /**
     * Gets an after each closure by the given filename.
     */
    public function get(string $filename): Closure
    {
        $afterEach = $this->state[$filename] ?? NullClosure::create();

        return ChainableClosure::bound(function (): void {
            if (class_exists(Mockery::class)) {
                if ($container = Mockery::getContainer()) {
                    /* @phpstan-ignore-next-line */
                    $this->addToAssertionCount($container->mockery_getExpectationCount());
                }

                Mockery::close();
            }
        }, $afterEach);
    }
}
