<?php

declare(strict_types=1);

namespace Pest\ArchPresets;

use Closure;
use Pest\Arch\Contracts\ArchExpectation;
use Pest\Expectation;

/**
 * @internal
 */
final class Custom extends AbstractPreset
{
    /**
     * Creates a new preset instance.
     *
     * @param  array<int, string>  $userNamespaces
     * @param  Closure(array<int, string>): array<Expectation<mixed>|ArchExpectation>  $execute
     */
    public function __construct(
        private readonly array $userNamespaces,
        private readonly string $name,
        private readonly Closure $execute,
    ) {
        parent::__construct($userNamespaces);
    }

    /**
     * Returns the name of the preset.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Executes the arch preset.
     */
    public function execute(): void
    {
        $this->expectations = ($this->execute)($this->userNamespaces);
    }
}
