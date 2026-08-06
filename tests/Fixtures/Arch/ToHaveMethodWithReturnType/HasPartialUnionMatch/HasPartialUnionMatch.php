<?php

declare(strict_types=1);

namespace Tests\Fixtures\Arch\ToHaveMethodWithReturnType\HasPartialUnionMatch;

class HasPartialUnionMatch
{
    public function foo(): string|int|null {}
}
