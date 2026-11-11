<?php

use Pest\Plugins\Coverage;
use Symfony\Component\Console\Output\NullOutput;

test('compute comparable coverage', function (float $givenValue, float $expectedValue): void {
    $output = new NullOutput;

    $plugin = new Coverage($output);

    $comparableCoverage = (fn () => $this->computeComparableCoverage($givenValue))->call($plugin);

    expect($comparableCoverage)->toBe($expectedValue);
})->with([
    [0, 0],
    [0.5, 0.5],
    [1.0, 1.0],
    [32.51, 32.5],
    [32.12312321312312, 32.1],
    [32.53333333333333, 32.5],
    [32.57777771232132, 32.5],
    [100.0, 100.0],
]);
