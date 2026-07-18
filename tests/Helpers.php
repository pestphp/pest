<?php

use Pest\PendingCalls\TestCall;
use Pest\Support\HigherOrderTapProxy;
use PHPUnit\Framework\TestCase;

/**
 * @return TestCase
 */
function myAssertTrue($value): HigherOrderTapProxy|TestCall
{
    test()->assertTrue($value);

    return test();
}
