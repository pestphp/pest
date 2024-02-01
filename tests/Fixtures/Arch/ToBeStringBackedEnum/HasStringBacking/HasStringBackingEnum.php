<?php

declare(strict_types=1);

namespace Tests\Fixtures\Arch\ToBeStringBackedEnum\HasStringBacking;

enum HasStringBackingEnum: string
{
    case StringBacked = 'Testing';
}
