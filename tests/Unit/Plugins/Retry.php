<?php

use Pest\Plugins\Retry;

beforeEach(fn () => Retry::$retrying = false);

afterEach(fn () => Retry::$retrying = false);

it('retries if --retry argument is used', function () {
    $retry = new Retry();

    $retry->handleArguments(['--retry']);

    expect(Retry::$retrying)->toBeTrue();
});
