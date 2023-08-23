<?php

namespace Tests\Fixtures\Features;

enum BackedStringEnumeration: string
{
    case Foo = 'foo';
    case Bar = 'bar';
}

enum BackedIntEnumeration: int
{
    case Foo = 1;
    case Bar = 2;
}
