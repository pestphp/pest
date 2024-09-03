<?php

use Pest\PendingCalls\TestCall;
use PHPUnit\Framework\TestCase;

pest()->use(Gettable::class);

/**
 * @return TestCase|TestCall|Gettable
 */
function get(string $route)
{
    return test()->get($route);
}

trait Gettable
{
    /**
     * @return TestCase|TestCall|Gettable
     */
    public function get(string $route)
    {
        expect($route)->not->toBeEmpty();

        return $this;
    }
}

get('foo'); // not incomplete because closure is created...
get('foo')->get('bar')->expect(true)->toBeTrue();
get('foo')->expect(true)->toBeTrue();

describe('a "describe" group of tests', function () {
    get('foo'); // not incomplete because closure is created...
    get('foo')->get('bar')->expect(true)->toBeTrue();
    get('foo')->expect(true)->toBeTrue();
});
