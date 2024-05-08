<?php

/**
 * @return \PHPUnit\Framework\TestCase
 */
function myAssertTrue($value)
{
    test()->assertTrue($value);

    return test();
}
