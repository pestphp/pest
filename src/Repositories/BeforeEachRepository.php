<?php

declare(strict_types=1);

namespace Pest\Repositories;

use Closure;
use Pest\PendingCalls\BeforeEachCall;
use Pest\Support\ChainableClosure;
use Pest\Support\NullClosure;

/**
 * @internal
 */
final class BeforeEachRepository
{
    /**
     * @var array<string, array{0: Closure, 1: Closure}>
     */
    private array $state = [];

    /**
     * Sets a before each closure.
     */
    public function set(string $filename, BeforeEachCall $beforeEachCall, Closure $beforeEachTestCall, Closure $beforeEachTestCase): void
    {
        if (array_key_exists($filename, $this->state)) {
            [$fromBeforeEachTestCall, $fromBeforeEachTestCase] = $this->state[$filename];

            $beforeEachTestCall = ChainableClosure::unbound($fromBeforeEachTestCall, $beforeEachTestCall);
            $beforeEachTestCase = ChainableClosure::bound($fromBeforeEachTestCase, $beforeEachTestCase)->bindTo($beforeEachCall, $beforeEachCall::class);
            assert($beforeEachTestCase instanceof Closure);
        }

        $this->state[$filename] = [$beforeEachTestCall, $beforeEachTestCase];
    }

    /**
     * Gets a before each closure by the given filename.
     *
     * @return array{0: Closure, 1: Closure}
     */
    public function get(string $filename): array
    {
        $closures = $this->state[$filename] ?? [];

        return [
            $closures[0] ?? NullClosure::create(),
            $closures[1] ?? NullClosure::create(),
        ];
    }
}
