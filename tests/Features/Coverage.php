<?php

use Pest\Plugins\Coverage as CoveragePlugin;
use Pest\Support\Coverage;
use Symfony\Component\Console\Output\ConsoleOutput;

it('has plugin')->assertTrue(class_exists(CoveragePlugin::class));

it('adds coverage if --coverage exist', function () {
    $plugin = new CoveragePlugin(new ConsoleOutput());

    expect($plugin->coverage)->toBeFalse();
    $arguments = $plugin->handleArguments([]);
    expect($arguments)->toEqual([]);
    expect($plugin->coverage)->toBeFalse();

    $arguments = $plugin->handleArguments(['--coverage']);
    expect($arguments)->toEqual(['--coverage-php', Coverage::getPath()]);
    expect($plugin->coverage)->toBeTrue();
});

it('adds coverage if --min exist', function () {
    $plugin = new CoveragePlugin(new ConsoleOutput());
    expect($plugin->coverageMin)->toEqual(0.0);

    expect($plugin->coverage)->toBeFalse();
    $plugin->handleArguments([]);
    expect($plugin->coverageMin)->toEqual(0.0);

    $plugin->handleArguments(['--min=2']);
    expect($plugin->coverageMin)->toEqual(2.0);

    $plugin->handleArguments(['--min=2.4']);
    expect($plugin->coverageMin)->toEqual(2.4);
});

it('generates coverage based on file input', function () {
    expect(Coverage::getMissingCoverage(new class()
    {
        public function lineCoverageData(): array
        {
            return [
                1 => ['foo'],
                2 => ['bar'],
                4 => [],
                5 => [],
                6 => [],
                7 => null,
                100 => null,
                101 => ['foo'],
                102 => [],
            ];
        }
    }))->toEqual([
        '4..6', '102',
    ]);
});
