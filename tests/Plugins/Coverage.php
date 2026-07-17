<?php

use Pest\Plugins\Coverage;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

test('compute comparable coverage', function (float $givenValue, float $expectedValue) {
    $plugin = new Coverage(new NullOutput);

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

test('apply thresholds', function (float $coverage, ?float $min, ?float $exactly, int $expectedExitCode) {
    $output = new BufferedOutput;
    $plugin = new Coverage($output);
    $plugin->coverageMin = $min ?? 0.0;
    $plugin->coverageExactly = $exactly;

    $exitCode = (fn () => $this->applyThresholds($coverage))->call($plugin);

    expect($exitCode)->toBe($expectedExitCode);

    if ($expectedExitCode === 1) {
        expect($output->fetch())->toContain('FAIL');
    }
})->with([
    'min pass' => [91.5, 80.0, null, 0],
    'min fail' => [91.5, 95.0, null, 1],
    'exactly pass' => [91.5, null, 91.5, 0],
    'exactly fail' => [91.5, null, 95.0, 1],
]);

test('strip shard blocked report flags', function (array $args, array $expected, bool $expectWarn) {
    $output = new BufferedOutput;
    $plugin = new Coverage($output);

    $filtered = (fn () => $this->stripShardBlockedReportFlags($args))->call($plugin);

    expect($filtered)->toBe($expected);

    $expectWarn
        ? expect($output->fetch())->toContain('WARN')
        : expect($output->fetch())->toBe('');
})->with([
    'inline value flag' => [['--coverage-html=out', '--compact'], ['--compact'], true],
    'space-separated flag' => [['--compact', '--coverage-clover', 'clover.xml'], ['--compact'], true],
    'no blocked flags' => [['--compact', '--stop-on-failure'], ['--compact', '--stop-on-failure'], false],
]);

test('apply thresholds returns 0 when coverage meets min', function () {
    $plugin = new Coverage(new NullOutput);
    $plugin->coverageMin = 80.0;

    $exitCode = (fn () => $this->applyThresholds(91.5))->call($plugin);

    expect($exitCode)->toBe(0);
});

test('apply thresholds returns 1 and writes FAIL when coverage is below min', function () {
    $output = new BufferedOutput;
    $plugin = new Coverage($output);
    $plugin->coverageMin = 95.0;

    $exitCode = (fn () => $this->applyThresholds(91.5))->call($plugin);

    expect($exitCode)->toBe(1)
        ->and($output->fetch())->toContain('95.0')->toContain('91.5');
});

test('apply thresholds returns 0 when coverage matches exactly', function () {
    $plugin = new Coverage(new NullOutput);
    $plugin->coverageExactly = 91.5;

    $exitCode = (fn () => $this->applyThresholds(91.5))->call($plugin);

    expect($exitCode)->toBe(0);
});

test('apply thresholds returns 1 and writes FAIL when coverage does not match exactly', function () {
    $output = new BufferedOutput;
    $plugin = new Coverage($output);
    $plugin->coverageExactly = 95.0;

    $exitCode = (fn () => $this->applyThresholds(91.5))->call($plugin);

    expect($exitCode)->toBe(1)
        ->and($output->fetch())->toContain('95.0')->toContain('91.5');
});

test('parse threshold options sets coverageMin', function () {
    $plugin = new Coverage(new NullOutput);

    (fn () => $this->parseThresholdOptions(['--min=42.5']))->call($plugin);

    expect($plugin->coverageMin)->toBe(42.5);
});

test('parse threshold options sets coverageExactly', function () {
    $plugin = new Coverage(new NullOutput);

    (fn () => $this->parseThresholdOptions(['--exactly=75.0']))->call($plugin);

    expect($plugin->coverageExactly)->toBe(75.0);
});

test('parse threshold options sets showOnlyCovered', function () {
    $plugin = new Coverage(new NullOutput);

    (fn () => $this->parseThresholdOptions(['--only-covered']))->call($plugin);

    expect($plugin->showOnlyCovered)->toBeTrue();
});

test('parse threshold options ignores unrelated flags', function () {
    $plugin = new Coverage(new NullOutput);

    (fn () => $this->parseThresholdOptions(['--compact', '--verbose']))->call($plugin);

    expect($plugin->coverageMin)->toBe(0.0)
        ->and($plugin->coverageExactly)->toBeNull()
        ->and($plugin->showOnlyCovered)->toBeFalse();
});

test('detect shard parses equals format', function () {
    $plugin = new Coverage(new NullOutput);

    $result = (fn () => $this->detectShard(['--shard=2/5']))->call($plugin);

    expect($result)->toBe([2, 5]);
});

test('detect shard parses space format', function () {
    $plugin = new Coverage(new NullOutput);

    $result = (fn () => $this->detectShard(['--shard', '3/4']))->call($plugin);

    expect($result)->toBe([3, 4]);
});

test('detect shard returns null when absent', function () {
    $plugin = new Coverage(new NullOutput);

    $result = (fn () => $this->detectShard(['--compact', '--coverage']))->call($plugin);

    expect($result)->toBeNull();
});

test('has shards coverage flag detects --shards-coverage', function () {
    $plugin = new Coverage(new NullOutput);

    expect((fn () => $this->hasShardsCoverageFlag(['--shards-coverage']))->call($plugin))->toBeTrue()
        ->and((fn () => $this->hasShardsCoverageFlag(['--coverage']))->call($plugin))->toBeFalse();
});

test('pop shards coverage flags removes --shards-coverage and --clean', function () {
    $plugin = new Coverage(new NullOutput);

    $remaining = (fn () => $this->popShardsCoverageFlags(['--shards-coverage', '--min=80', '--clean']))->call($plugin);
    $isClean = (fn () => $this->shardsCoverageClean)->call($plugin);

    expect($remaining)->toBe(['--min=80'])
        ->and($isClean)->toBeTrue();
});

test('strip shard blocked report flags removes --coverage-html', function () {
    $output = new BufferedOutput;
    $plugin = new Coverage($output);

    $filtered = (fn () => $this->stripShardBlockedReportFlags(['--coverage-html=out', '--compact']))->call($plugin);

    expect($filtered)->toBe(['--compact'])
        ->and($output->fetch())->toContain('WARN');
});

test('strip shard blocked report flags removes --coverage-clover as separate arg', function () {
    $output = new BufferedOutput;
    $plugin = new Coverage($output);

    $filtered = (fn () => $this->stripShardBlockedReportFlags(['--compact', '--coverage-clover', 'clover.xml']))->call($plugin);

    expect($filtered)->toBe(['--compact'])
        ->and($output->fetch())->toContain('WARN');
});

test('strip shard blocked report flags keeps non-blocked flags without warning', function () {
    $output = new BufferedOutput;
    $plugin = new Coverage($output);

    $filtered = (fn () => $this->stripShardBlockedReportFlags(['--compact', '--stop-on-failure']))->call($plugin);

    expect($filtered)->toBe(['--compact', '--stop-on-failure'])
        ->and($output->fetch())->toBe('');
});

test('getCoverageDir returns path under .pest/coverage', function () {
    $plugin = new Coverage(new NullOutput);

    $path = (fn () => $this->getCoverageDir())->call($plugin);

    expect($path)->toEndWith('.pest'.DIRECTORY_SEPARATOR.'coverage');
});

test('mergeAndReportShardsCoverage returns -1 when coverage directory is empty', function () {
    $output = new BufferedOutput;
    $plugin = new Coverage($output);

    $result = (fn () => $this->mergeAndReportShardsCoverage())->call($plugin);

    expect($result)->toBe(-1.0)
        ->and($output->fetch())->toContain('ERROR');
});
