<?php

declare(strict_types=1);

namespace Tests\Fixtures\Arch\ToBeIntBackedEnum\HasIntBacking;

enum HasIntBackingEnum: int
{
    case IntBacked = 1;
}
