<?php

declare(strict_types=1);

use Pest\Plugins\Tia;
use Pest\Plugins\Tia\Contracts\State;
use Pest\Plugins\Tia\CoverageMerger;
use Pest\Plugins\Tia\FileState;
use Pest\Support\Container;
use Pest\Support\Coverage;
use Pest\Support\Str;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;

test('a remeasured file drops line numbers that the current file no longer has', function (): void {
    $source = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-stale-lines-'.bin2hex(random_bytes(4)).'.php';
    $test = Str::class.'::endsWith';

    $cached = coverageWithLines([
        0 => $test,
    ], [
        $source => [
            9 => [0 => 1],
            14 => [],
        ],
    ]);
    $current = coverageWithLines([
        0 => $test,
    ], [
        $source => [
            9 => [0 => 1],
            10 => [],
        ],
    ]);

    $merged = mergeCoverageLines($cached, $current);
    $lines = $merged->getData(true)->lineCoverage()[$source];

    expect(array_keys($lines))->toEqualCanonicalizing([9, 10])
        ->and($lines)->not->toHaveKey(14);

    if (is_file($source)) {
        unlink($source);
    }
})->skip(! Coverage::isAvailable(), 'Coverage is not available');

function coverageWithLines(array $testIds, array $lines): CodeCoverage
{
    $filter = new Filter;
    $coverage = new CodeCoverage(new Selector()->forLineCoverage($filter), $filter);
    $data = $coverage->getData(true);
    $data->setTestIds($testIds);
    $data->setLineCoverage($lines);
    $coverage->setData($data);

    return $coverage;
}

function mergeCoverageLines(CodeCoverage $cached, CodeCoverage $current): CodeCoverage
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-coverage-lines-'.bin2hex(random_bytes(4));
    mkdir($directory);
    $report = $directory.DIRECTORY_SEPARATOR.'coverage.php';
    $container = Container::getInstance();
    $previous = $container->get(State::class);
    $container->add(State::class, new FileState($directory));

    file_put_contents($report, '<?php return unserialize('.var_export(serialize($current), true).");\n");
    $compressed = gzencode(serialize($cached));
    $state = $container->get(State::class);
    assert($state instanceof FileState);
    $state->write(Tia::KEY_COVERAGE_CACHE, $compressed === false ? serialize($cached) : $compressed);
    $state->write(Tia::KEY_COVERAGE_MARKER, '');

    try {
        CoverageMerger::applyIfMarked($report);

        $merged = require $report;
        assert($merged instanceof CodeCoverage);

        return $merged;
    } finally {
        $container->add(State::class, $previous);

        if (is_file($report)) {
            unlink($report);
        }

        foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($directory);
    }
}
