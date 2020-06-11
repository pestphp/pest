<?php

namespace Tests;

use Mockery;
use Mockery\MockInterface;

function mock(string $class): MockInterface
{
    return Mockery::mock($class);
}
