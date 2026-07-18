<?php

use Pest\Plugins\Coverage as CoveragePlugin;
use Pest\Support\Coverage;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;

it('has plugin')->assertTrue(class_exists(CoveragePlugin::class));

it('adds coverage if --coverage exist', function (): void {
    $plugin = new CoveragePlugin(new ConsoleOutput);

    expect($plugin->coverage)->toBeFalse();
    $arguments = $plugin->handleArguments([]);
    expect($arguments)->toBeEmpty()
        ->and($plugin->coverage)->toBeFalse();

    $arguments = $plugin->handleArguments(['--coverage']);
    expect($arguments)->toEqual(['--coverage-php', Coverage::getPath()])
        ->and($plugin->coverage)->toBeTrue();
})->skip(! Coverage::isAvailable() || ! function_exists('xdebug_info') || ! in_array('coverage', xdebug_info('mode'), true), 'Coverage is not available');

it('adds coverage if --min exist', function (): void {
    $plugin = new CoveragePlugin(new ConsoleOutput);
    expect($plugin->coverageMin)->toEqual(0.0)
        ->and($plugin->coverage)->toBeFalse();

    $plugin->handleArguments([]);
    expect($plugin->coverageMin)->toEqual(0.0);

    $plugin->handleArguments(['--min=2']);
    expect($plugin->coverageMin)->toEqual(2.0);

    $plugin->handleArguments(['--min=2.4']);
    expect($plugin->coverageMin)->toEqual(2.4);
});

it('adds coverage if --exactly exist', function () {
    $plugin = new CoveragePlugin(new ConsoleOutput);

    $plugin->handleArguments(['--exactly=50']);
    expect($plugin->coverageExactly)->toEqual(50.0);

    $plugin->handleArguments(['--exactly=50.5']);
    expect($plugin->coverageExactly)->toEqual(50.5);
});

it('adds coverage if --only-covered exist', function () {
    $plugin = new CoveragePlugin(new ConsoleOutput);

    $plugin->handleArguments(['--only-covered']);
    expect($plugin->showOnlyCovered)->toBeTrue();
});

it('routes --coverage-php to .pest/coverage/{n}.cov when --shard is used', function () {
    $plugin = new CoveragePlugin(new ConsoleOutput);

    $arguments = $plugin->handleArguments(['--coverage', '--shard=1/3']);

    $phpIdx = array_search('--coverage-php', $arguments, true);
    expect($phpIdx)->not->toBeFalse();

    $covPath = $arguments[$phpIdx + 1];
    expect($covPath)->toEndWith('.pest'.DIRECTORY_SEPARATOR.'coverage'.DIRECTORY_SEPARATOR.'1.cov');
})->skip(! Coverage::isAvailable() || ! function_exists('xdebug_info') || ! in_array('coverage', xdebug_info('mode'), true), 'Coverage is not available');

it('strips blocked report flags and warns when --shard is used', function () {
    $output = new BufferedOutput;
    $plugin = new CoveragePlugin($output);

    $arguments = $plugin->handleArguments(['--coverage', '--shard=1/2', '--coverage-html=out']);

    expect($arguments)->not->toContain('--coverage-html=out')
        ->and($output->fetch())->toContain('WARN');
})->skip(! Coverage::isAvailable() || ! function_exists('xdebug_info') || ! in_array('coverage', xdebug_info('mode'), true), 'Coverage is not available');

it('generates coverage based on file input', function (): void {
    expect(Coverage::getMissingCoverage(new class
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
