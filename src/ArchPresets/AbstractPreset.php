<?php

declare(strict_types=1);

namespace Pest\ArchPresets;

use Pest\Arch\Contracts\ArchExpectation;

/**
 * @internal
 */
abstract class AbstractPreset
{
    /**
     * Creates a new preset instance.
     *
     * @param  array<int, string>  $userNamespaces
     * @param  array<int, ArchExpectation>  $expectations
     */
    final public function __construct(// @phpstan-ignore-line
        protected array $userNamespaces,
        protected array $expectations = [],
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
     * Updates expectations
     *
     * @param  array<ArchExpectation>  $expectations
     *
     * @internal
     */
    final protected function updateExpectations(array $expectations): void
    {
        $this->expectations = array_merge($this->expectations, $expectations);
    }

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
     * Flushes the expectations.
     */
    final public function flush(): void
    {
        $this->expectations = [];
    }
}
