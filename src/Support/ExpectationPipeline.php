<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;

/**
 * @internal
 */
final class ExpectationPipeline
{
    /**
     * The list of pipes.
     *
     * @var array<int, Closure>
     */
    private array $pipes = [];

    /**
     * The list of passables.
     *
     * @var array<int, mixed>
     */
    private array $passables;

    /**
     * The expectation closure.
     */
    private Closure $closure;

    /**
     * Creates a new instance of Expectation Pipeline.
     */
    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
    }

    /**
     * Creates a new instance of Expectation Pipeline with given closure.
     */
    public static function for(Closure $closure): self
    {
        return new self($closure);
    }

    /**
     * Sets the list of passables.
     */
    public function send(mixed ...$passables): self
    {
        $this->passables = array_values($passables);

        return $this;
    }

    /**
     * Sets the list of pipes.
     *
     * @param array<int, Closure> $pipes
     */
    public function through(array $pipes): self
    {
        $this->pipes = $pipes;

        return $this;
    }

    /**
     * Runs the pipeline.
     */
    public function run(): void
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            function (): void {
                ($this->closure)(...$this->passables);
            }
        );

        $pipeline();
    }

    /**
     * Get a Closure that will carry of the expectation.
     */
    public function carry(): Closure
    {
        return function ($stack, $pipe): Closure {
            return function () use ($stack, $pipe) {
                return $pipe($stack, ...$this->passables);
            };
        };
    }
}
