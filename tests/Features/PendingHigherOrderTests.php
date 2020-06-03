<?php

use PHPUnit\Framework\TestCase;

uses(Gettable::class);

/**
 * @return TestCase|Gettable
 */
function get(string $route)
{
    return test()->get($route);
}

trait Gettable
{
    /**
     * @return TestCase|Gettable
     */
    public function get(string $route)
    {
        assertNotEmpty($route);

        return $this;
    }
}

get('foo')->get('bar')->assertTrue(true);
get('foo')->assertTrue(true);
