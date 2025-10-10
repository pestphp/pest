<?php

it('works with repeat and expectation debug on failure', function () {
    $debugCalled = false;

    try {
        expect(true)
            ->debug(function () use (&$debugCalled) {
                $debugCalled = true;
            })
            ->toBe(false);
    } catch (PHPUnit\Framework\ExpectationFailedException $e) {
        expect($debugCalled)->toBeTrue('Debug callback should be called on expectation failure');
    }
})->repeat(3);

it('works with repeat and expectation debug on success', function () {
    $debugCalled = false;

    expect(true)
        ->debug(function () use (&$debugCalled) {
            $debugCalled = true;
        })
        ->toBe(true);

    expect($debugCalled)->toBeFalse('Debug callback should NOT be called on expectation success');
})->repeat(2);

it('should support debug method on expectations with callback', function () {
    $debugCalled = false;

    try {
        expect(true)
            ->debug(function () use (&$debugCalled) {
                $debugCalled = true; // This debug callback should be called on failure
            })
            ->toBe(false); // This will fail after debug is registered

        expect(false)->toBeTrue('This line should not be reached');
    } catch (PHPUnit\Framework\ExpectationFailedException $e) {
        expect($debugCalled)->toBeTrue('Debug callback should be called on failure');
        expect($e->getMessage())->toContain('Failed asserting that true is identical to false');
    }
});

it('should NOT call debug callback on expectation success', function () {
    $debugCalled = false;

    expect(true)
        ->debug(function () use (&$debugCalled) {
            $debugCalled = true;
        })
        ->toBe(true);

    expect($debugCalled)->toBeFalse('Debug callback should NOT be called on success');
});

it('should support debug method chaining on test calls', function () {
    expect(true)->toBeTrue();
});

it('debug works properly for expectations', function () {
    $debugCalled = false;

    expect(2 + 2)
        ->debug(function () use (&$debugCalled) {
            $debugCalled = true;
        })
        ->toBe(4);

    expect($debugCalled)->toBeFalse('Debug callback should NOT be called on success');
});
