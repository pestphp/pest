<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;

final class Pipeline
{
    /** @var array<Closure> */
    private $pipes = [];

    /** @var array<mixed> */
    private $passable;

    /**
     * @param array<mixed> $passable
     */
    public function __construct(...$passable)
    {
        $this->passable = $passable;
    }

    /**
     * @param array<mixed> $passable
     */
    public static function send(...$passable): self
    {
        return new self(...$passable);
    }

    /**
     * @param array<Closure> $pipes
     */
    public function through(array $pipes): self
    {
        $this->pipes = $pipes;

        return $this;
    }

    public function finally(Closure $finalClosure): void
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            $this->prepareFinalClosure($finalClosure)
        );

        $pipeline(...$this->passable);
    }

    public function carry(): Closure
    {
        return function ($stack, $pipe): Closure {
            return function (...$passable) use ($stack, $pipe) {
                return $pipe($stack, ...$passable);
            };
        };
    }

    private function prepareFinalClosure(Closure $finalClosure): Closure
    {
        return function (...$passable) use ($finalClosure) {
            return $finalClosure($passable);
        };
    }
}
