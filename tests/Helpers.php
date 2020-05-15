<?php

use Mockery\MockInterface;

function mock(string $class): MockInterface
{
    return Mockery::mock($class);
}
