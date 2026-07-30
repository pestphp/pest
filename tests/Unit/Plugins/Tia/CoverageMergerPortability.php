<?php

declare(strict_types=1);

use Pest\Plugins\Tia;
use Pest\Plugins\Tia\Contracts\State;
use Pest\Plugins\Tia\CoverageMerger;
use Pest\Plugins\Tia\FileState;
use Pest\Support\Container;
use Pest\TestSuite;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Data\ProcessedCodeCoverageData;
use SebastianBergmann\CodeCoverage\Data\RawCodeCoverageData;
use SebastianBergmann\CodeCoverage\Driver\Driver;
use SebastianBergmann\CodeCoverage\Filter;

final class CoverageMergerPortabilityFakeDriver extends Driver
{
    public function name(): string
    {
        return 'fake';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function start(): void {}

    public function stop(): RawCodeCoverageData
    {
        return RawCodeCoverageData::fromXdebugWithoutPathCoverage([]);
    }
}

/**
 * @param  array<string, array<int, array<int, string>>>  $lineCoverage
 * @param  array<string, array{size: string, status: string}>  $tests
 */
function coverageMergerPortabilityCoverage(array $lineCoverage, array $tests = []): CodeCoverage
{
    $coverage = new CodeCoverage(new CoverageMergerPortabilityFakeDriver, new Filter);

    $data = new ProcessedCodeCoverageData;
    $data->setLineCoverage($lineCoverage);

    $coverage->setData($data);
    $coverage->setTests($tests);

    return $coverage;
}

function coverageMergerPortabilityReport(string $reportPath, CodeCoverage $coverage): void
{
    file_put_contents(
        $reportPath,
        '<?php return unserialize('.var_export(serialize($coverage), true).");\n",
    );
}

beforeEach(function (): void {
    $this->stateDir = sys_get_temp_dir().'/pest-tia-coverage-merger-'.bin2hex(random_bytes(4));
    mkdir($this->stateDir, 0755, true);

    $this->reportPath = $this->stateDir.'/coverage-report.php';

    try {
        $this->previousState = Container::getInstance()->get(State::class);
    } catch (Throwable) {
        $this->previousState = null;
    }

    Container::getInstance()->add(State::class, new FileState($this->stateDir));

    $this->projectRoot = rtrim(TestSuite::getInstance()->rootPath, '/\\');
});

afterEach(function (): void {
    if ($this->previousState instanceof State) {
        Container::getInstance()->add(State::class, $this->previousState);
    }

    foreach (glob($this->stateDir.'/*') ?: [] as $file) {
        @unlink($file);
    }

    @rmdir($this->stateDir);
});

it('discards a cache recorded on another machine and reseeds from the current run', function (): void {
    $state = Container::getInstance()->get(State::class);
    assert($state instanceof State);

    $foreign = coverageMergerPortabilityCoverage([
        '/home/runner/work/acme/acme/src/Example.php' => [10 => ['cached-test']],
    ], ['cached-test' => ['size' => 'unknown', 'status' => 'success']]);

    $state->write(Tia::KEY_COVERAGE_CACHE, (string) gzencode(serialize($foreign)));
    $state->write(Tia::KEY_COVERAGE_MARKER, '');

    $localFile = $this->projectRoot.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Pest.php';

    coverageMergerPortabilityReport($this->reportPath, coverageMergerPortabilityCoverage([
        $localFile => [5 => ['fresh-test']],
    ], ['fresh-test' => ['size' => 'unknown', 'status' => 'success']]));

    CoverageMerger::applyIfMarked($this->reportPath);

    expect($state->exists(Tia::KEY_COVERAGE_MARKER))->toBeFalse();

    $cachedBytes = $state->read(Tia::KEY_COVERAGE_CACHE);
    expect($cachedBytes)->not->toBeNull();

    $cache = unserialize((string) gzdecode((string) $cachedBytes));
    expect($cache)->toBeInstanceOf(CodeCoverage::class);
    assert($cache instanceof CodeCoverage);

    expect($cache->getData(true)->coveredFiles())->toBe(['src/Pest.php']);
});

it('rebases relative cached paths onto the local project root before merging', function (): void {
    $state = Container::getInstance()->get(State::class);
    assert($state instanceof State);

    $cached = coverageMergerPortabilityCoverage([
        'src/Pest.php' => [10 => ['cached-test']],
    ], ['cached-test' => ['size' => 'unknown', 'status' => 'success']]);

    $state->write(Tia::KEY_COVERAGE_CACHE, (string) gzencode(serialize($cached)));
    $state->write(Tia::KEY_COVERAGE_MARKER, '');

    $cachedFile = $this->projectRoot.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Pest.php';
    $freshFile = $this->projectRoot.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Panic.php';

    coverageMergerPortabilityReport($this->reportPath, coverageMergerPortabilityCoverage([
        $freshFile => [21 => ['fresh-test']],
    ], ['fresh-test' => ['size' => 'unknown', 'status' => 'success']]));

    CoverageMerger::applyIfMarked($this->reportPath);

    $merged = require $this->reportPath;
    expect($merged)->toBeInstanceOf(CodeCoverage::class);
    assert($merged instanceof CodeCoverage);

    $lineCoverage = $merged->getData(true)->lineCoverage();
    expect($lineCoverage)->toHaveKeys([$cachedFile, $freshFile])
        ->and($lineCoverage[$cachedFile][10])->toBe(['cached-test'])
        ->and($lineCoverage[$freshFile][21])->toBe(['fresh-test']);

    $cache = unserialize((string) gzdecode((string) $state->read(Tia::KEY_COVERAGE_CACHE)));
    assert($cache instanceof CodeCoverage);

    expect($cache->getData(true)->coveredFiles())->toBe(['src/Panic.php', 'src/Pest.php']);
});

it('still merges a same-machine cache that stores absolute paths', function (): void {
    $state = Container::getInstance()->get(State::class);
    assert($state instanceof State);

    $localFile = $this->projectRoot.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Pest.php';

    $cached = coverageMergerPortabilityCoverage([
        $localFile => [10 => ['cached-test'], 12 => ['cached-test']],
    ], ['cached-test' => ['size' => 'unknown', 'status' => 'success']]);

    $state->write(Tia::KEY_COVERAGE_CACHE, (string) gzencode(serialize($cached)));
    $state->write(Tia::KEY_COVERAGE_MARKER, '');

    coverageMergerPortabilityReport($this->reportPath, coverageMergerPortabilityCoverage([
        $localFile => [11 => ['fresh-test']],
    ], ['fresh-test' => ['size' => 'unknown', 'status' => 'success']]));

    CoverageMerger::applyIfMarked($this->reportPath);

    $merged = require $this->reportPath;
    assert($merged instanceof CodeCoverage);

    $lines = $merged->getData(true)->lineCoverage()[$localFile];
    ksort($lines);

    expect($lines)->toBe([
        10 => ['cached-test'],
        11 => ['fresh-test'],
        12 => ['cached-test'],
    ]);

    $cache = unserialize((string) gzdecode((string) $state->read(Tia::KEY_COVERAGE_CACHE)));
    assert($cache instanceof CodeCoverage);

    expect($cache->getData(true)->coveredFiles())->toBe(['src/Pest.php']);
});
