<?php

declare(strict_types=1);

namespace Pest\ArchPresets;

use Pest\Arch\Contracts\ArchExpectation;
use Pest\Expectation;

/**
 * @internal
 */
abstract class AbstractPreset // @pest-arch-ignore-line
{
    /**
     * @var array<int, Expectation<mixed>|ArchExpectation>
     */
    protected array $expectations = [];

    /**
     * @param  array<int, string>  $userNamespaces
     */
    public function __construct(
        private readonly array $userNamespaces,
    ) {
        //
    }

    /**
     * @internal
     */
    abstract public function execute(): void;

    /**
     * @param  array<int, string>|string  $targetsOrDependencies
     */
    final public function ignoring(array|string $targetsOrDependencies): void
    {
        $this->expectations = array_map(
            fn (ArchExpectation|Expectation $expectation): Expectation|ArchExpectation => $expectation instanceof ArchExpectation ? $expectation->ignoring($targetsOrDependencies) : $expectation,
            $this->expectations,
        );
    }

    /**
     * @param  callable(Expectation<string>): ArchExpectation  ...$callbacks
     */
    final public function eachUserNamespace(callable ...$callbacks): void
    {
        foreach ($this->userNamespaces as $namespace) {
            foreach ($callbacks as $callback) {
                $this->expectations[] = $callback(expect($namespace));
            }
        }
    }

    final public function flush(): void
    {
        $this->expectations = [];
    }
}
