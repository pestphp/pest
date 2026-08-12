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

    public function name(): string
    {
        return $this->name;
    }

    public function execute(): void
    {
        $this->expectations = ($this->execute)($this->userNamespaces);
    }
}
