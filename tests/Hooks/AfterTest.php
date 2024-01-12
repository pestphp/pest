<?php

test('after helper can be chained', function () {
    expect(true)->toBeTrue();
})->after(function () {
    // Example
});

test('after closure is called', function () use (&$afterWasCalled) {
    $afterWasCalled = true;

    expect(true)->toBeTrue();
})->after(function () use (&$afterWasCalled) {
    expect($afterWasCalled)->toBeTrue();
});
