<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;

final class ExpectationPipeline
{
    /** @var array<Closure> */
    private $pipes = [];

    /** @var array<mixed> */
    private $passable;

    /** @var Closure */
    private $expectationClosure;

    /** @var string */
    private $expectationName;

    public function __construct(string $expectationName, Closure $expectationClosure)
    {
        $this->expectationClosure = $expectationClosure;
        $this->expectationName    = $expectationName;
    }

    public static function for(string $expectationName, Closure $expectationClosure): self
    {
        return new self($expectationName, $expectationClosure);
    }

    /**
     * @param array<mixed> $passable
     */
    public function send(...$passable): self
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
