<?php

declare(strict_types=1);

namespace Tests\Fixtures\Arch\ToBeStringBackedEnum\HasIntBacking;

enum HasIntBackingEnum: int
{
    case IntBacked = 1;
}
