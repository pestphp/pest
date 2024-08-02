<?php

use Pest\Plugins\Retry;

it('orders by defects and stop on defects if when --retry is used ', function () {
    $retry = new Retry;

    $arguments = $retry->handleArguments(['--retry']);

    expect($arguments)->toBe([
        '--order-by=defects',
        '--stop-on-failure',
    ]);
});
