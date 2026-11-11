<?php

declare(strict_types=1);

namespace Pest\Support;

final readonly class Description implements \Stringable
{
    public function __construct(private string $description) {}

    public function __toString(): string
    {
        return $this->description;
    }
}
