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

    /** @var array<int|string, mixed>  */
    private array $passable;

    public function __construct(
        private Closure $expectationClosure
    ) {
        //..
    }

    public static function for(Closure $expectationClosure): ExpectationPipeline
    {
        return new self($expectationClosure);
    }

    public function send(...$passable): ExpectationPipeline
    {
        $this->passable = $passable;

        return $this;
    }

    /**
     * @param array<Closure> $pipes
     */
    public function through(array $pipes): ExpectationPipeline
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
        return fn ($stack, $pipe): Closure => fn () => $pipe($stack, ...$this->passable);
    }
}
