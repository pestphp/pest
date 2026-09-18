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

test('coverage options accept a space separated value', function (array $arguments, float $min, ?float $exactly): void {
    $plugin = new Coverage(new NullOutput);

    $plugin->handleArguments($arguments);

    expect($plugin->coverageMin)->toBe($min)
        ->and($plugin->coverageExactly)->toBe($exactly);
})->with([
    'min with equals sign' => [['--min=50'], 50.0, null],
    'min with space' => [['--min', '50'], 50.0, null],
    'exactly with equals sign' => [['--exactly=100'], 0.0, 100.0],
    'exactly with space' => [['--exactly', '100'], 0.0, 100.0],
    'both with space' => [['--min', '50', '--exactly', '100'], 50.0, 100.0],
]);

test('coverage options do not consume arguments owned by phpunit', function (): void {
    $plugin = new Coverage(new NullOutput);

    $arguments = $plugin->handleArguments(['--min', '50', '--filter', 'add', 'tests/Unit']);

    expect($plugin->coverageMin)->toBe(50.0)
        ->and($arguments)->toBe(['--filter', 'add', 'tests/Unit']);
});

test('value less coverage options do not consume the next argument', function (): void {
    $plugin = new Coverage(new NullOutput);

    $arguments = $plugin->handleArguments(['--only-covered', '--filter', 'add']);

    expect($plugin->showOnlyCovered)->toBeTrue()
        ->and($arguments)->toBe(['--filter', 'add']);
});
