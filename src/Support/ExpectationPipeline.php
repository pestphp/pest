<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;

/**
 * @internal
 */
final class ExpectationPipeline
{
    /** @var array<Closure> */
    private array $pipes = [];

    /** @var array<mixed> */
    private array $passable;

    private Closure $expectationClosure;

    public function __construct(Closure $expectationClosure)
    {
        $this->expectationClosure = $expectationClosure;
    }

    public static function for(Closure $expectationClosure): self
    {
        return new self($expectationClosure);
    }

    public function send(mixed ...$passable): self
    {
        $this->passable = $passable;

        return $this;
    }

    /**
     * @param array<Closure> $pipes
     */
    public function through(array $pipes): self
    {
        $this->pipes = $pipes;

        return $this;
    }

    public function run(): void
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            function (): void {
                ($this->expectationClosure)(...$this->passable);
            }
        );

        $pipeline();
    }

    public function carry(): Closure
    {
        return function ($stack, $pipe): Closure {
            return function () use ($stack, $pipe) {
                return $pipe($stack, ...$this->passable);
            };
        };
    }
}
