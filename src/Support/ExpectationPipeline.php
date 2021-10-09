<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;
use Pest\Exceptions\PipeException;
use ReflectionFunction;

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
        $this->expectationName = $expectationName;
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
            $this->expectationClosure
        );

        $pipeline(...$this->passable);
    }

    public function carry(): Closure
    {
        return function ($stack, $pipe): Closure {
            return function (...$passable) use ($stack, $pipe) {
                $this->checkOptionalParametersBecomeRequired($pipe);

                $passable = $this->preparePassable($passable);

                $passable[] = $stack;

                return $pipe(...$passable);
            };
        };
    }


    private function preparePassable(array $passable): array
    {
        $reflection = new ReflectionFunction($this->expectationClosure);

        $requiredParametersCount = $reflection->getNumberOfParameters();


        if (count($passable) < $requiredParametersCount) {
            foreach ($reflection->getParameters() as $index => $parameter) {
                if (!isset($passable[$index])) {
                    $passable[$index] = $parameter->getDefaultValue();
                }
            }
        }

        return $passable;
    }

    private function checkOptionalParametersBecomeRequired($pipe)
    {
        $reflection = new ReflectionFunction($pipe);

        foreach ($reflection->getParameters() as $parameter) {
            if ($parameter->isDefaultValueAvailable()) {
                /*
                 * TODO add pipeline blame in the exception message and a stronger clarification like
                 * “You’re attempting to pipe ‘toBe’, but haven’t added the $actual parameter to your pipe handler”
                 */
                throw PipeException::optionalParmetersShouldBecomeRequired($this->expectationName);
            }
        }
    }
}
