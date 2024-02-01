<?php

declare(strict_types=1);

namespace Tests\Fixtures\Arch\ToBeIntBackedEnum\HasStringBacking;

enum HasStringBackingEnum: string
{
    case StringBacked = 'Testing';
}
