<?php

declare(strict_types=1);

namespace Tests\Fixtures\Arch\ToHaveMethodWithReturnType\HasUnionReturnType;

class HasUnionReturnType
{
    public function foo(): string|int {}
}
