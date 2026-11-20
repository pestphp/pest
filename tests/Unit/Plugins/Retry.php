<?php

use Pest\Exceptions\InvalidOption;
use Pest\Plugins\Retry;

it('orders by defects and stop on defects if when --retry is used ', function (): void {
    $retry = new Retry;

    $arguments = $retry->handleArguments(['--retry']);

    expect($arguments)->toBe([
        '--order-by=defects',
        '--stop-on-failure',
    ]);
});

it('rejects retry with a parallel flag', function (string $parallelFlag): void {
    (new Retry)->handleArguments(['--retry', $parallelFlag]);
})->with(['--parallel', '-p'])->throws(
    InvalidOption::class,
    'The [--retry] option is not supported when running in parallel.',
);
