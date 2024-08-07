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
     * The expectations.
     *
     * @var array<int, ArchExpectation>
     */
    protected array $expectations = [];

    /**
     * Creates a new preset instance.
     *
     * @param  array<int, string>  $userNamespaces
     */
    final public function __construct(
        private readonly array $userNamespaces,
    ) {
        //
    }

    /**
     * Executes the arch preset.
     *
     * @internal
     */
    abstract public function execute(): void;

    /**
     * Ignores the given "targets" or "dependencies".
     *
     * @param  array<int, string>|string  $targetsOrDependencies
     */
    final public function ignoring(array|string $targetsOrDependencies): void
    {
        $this->expectations = array_map(
            fn (ArchExpectation $expectation): \Pest\Arch\Contracts\ArchExpectation => $expectation->ignoring($targetsOrDependencies),
            $this->expectations,
        );
    }

    /**
     * Runs the given callback for each namespace.
     *
     * @param  callable(Expectation<string|null>): ArchExpectation  ...$callbacks
     */
    final public function eachUserNamespace(callable ...$callbacks): void
    {
        foreach ($this->userNamespaces as $namespace) {
            foreach ($callbacks as $callback) {
                $this->expectations[] = $callback(expect($namespace));
            }
        }
    }

    /**
     * Flushes the expectations.
     */
    final public function flush(): void
    {
        $this->expectations = [];
    }
}
